<script setup lang="ts">
import { onMounted, computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useRunStore } from '@/stores/runs'
import { useAuthStore } from '@/stores/auth'
import { aiApi } from '@/api/ai'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import MetricCard from '@/components/ui/MetricCard.vue'
import StepTimeline from '@/components/StepTimeline.vue'
import DagVisualization from '@/components/DagVisualization.vue'
import type { AIFailureAnalysis, StepRun } from '@/types'

const route = useRoute()
const store = useRunStore()
const auth = useAuthStore()
const runId = route.params.id as string

const analyzing = ref(false)
const analysis = ref<AIFailureAnalysis | null>(null)
const activeTab = ref<'steps' | 'dag'>('steps')

onMounted(() => {
  store.fetchRun(runId)
})

const run = computed(() => store.currentRun)

const duration = computed(() => {
  if (!run.value?.started_at || !run.value?.completed_at) return '—'
  const ms = new Date(run.value.completed_at).getTime() - new Date(run.value.started_at).getTime()
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(1)}s`
})

const stepStatuses = computed(() => {
  if (!run.value?.step_runs) return {}
  const map: Record<string, string> = {}
  for (const step of run.value.step_runs) {
    map[step.step_id] = step.status
  }
  return map
})

const dagSteps = computed(() => {
  if (!run.value?.version?.definition?.steps) return []
  return run.value.version.definition.steps
})

const failedStep = computed<StepRun | undefined>(() =>
  run.value?.step_runs?.find((s) => s.status === 'failed')
)

async function analyzeFailure() {
  if (!failedStep.value || !run.value) return
  analyzing.value = true
  try {
    analysis.value = await aiApi.analyzeFailure({
      workflow_name: run.value.workflow?.name || 'Unknown',
      step_name: failedStep.value.step_name,
      step_type: failedStep.value.step_type,
      error_message: failedStep.value.error_message || 'Unknown error',
      attempt: failedStep.value.attempt,
    })
  } catch {
    // Silent fail
  } finally {
    analyzing.value = false
  }
}

function formatDate(date: string | null): string {
  if (!date) return '—'
  return new Date(date).toLocaleString()
}
</script>

<template>
  <div>
    <LoadingSpinner v-if="store.loading" text="Loading run details..." />

    <template v-else-if="run">
      <!-- Header -->
      <PageHeader :title="run.workflow?.name || 'Run Detail'" :subtitle="`Triggered ${formatDate(run.created_at)}`">
        <template #actions>
          <StatusBadge :status="run.status" :pulse="run.status === 'running'" />
        </template>
      </PageHeader>

      <!-- Stats row -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <MetricCard label="Status" :value="run.status" :color="run.status === 'success' ? 'success' : run.status === 'failed' ? 'danger' : 'info'" />
        <MetricCard label="Duration" :value="duration" />
        <MetricCard label="Trigger" :value="run.trigger_type" />
        <MetricCard label="Steps" :value="run.step_runs?.length || 0" :subtitle="`${run.step_runs?.filter(s => s.status === 'success').length || 0} completed`" />
      </div>

      <!-- Error banner -->
      <div v-if="run.error_message" class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <p class="text-sm font-medium text-red-800">Execution Failed</p>
            <p class="text-sm text-red-700 mt-1 font-mono">{{ run.error_message }}</p>
          </div>
          <button
            v-if="auth.canWrite && failedStep"
            @click="analyzeFailure"
            :disabled="analyzing"
            class="btn-secondary btn-sm flex-shrink-0"
          >
            {{ analyzing ? '🔄 Analyzing...' : '🤖 AI Diagnose' }}
          </button>
        </div>

        <!-- AI Analysis result -->
        <Transition name="fade">
          <div v-if="analysis" class="mt-4 p-4 bg-white rounded-lg border border-indigo-200">
            <div class="flex items-center gap-2 mb-2">
              <span class="text-sm font-semibold text-indigo-700">🤖 AI Analysis</span>
              <span class="badge-info text-[10px]">{{ (analysis.confidence * 100).toFixed(0) }}% confidence</span>
            </div>
            <p class="text-sm text-gray-700 mb-3">{{ analysis.diagnosis }}</p>
            <div class="space-y-1.5">
              <p class="text-xs font-medium text-gray-500">Suggestions:</p>
              <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                <li v-for="(suggestion, i) in analysis.suggestions" :key="i">{{ suggestion }}</li>
              </ul>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Tab switch: Steps vs DAG -->
      <div class="flex gap-1 mb-4 bg-gray-100 rounded-lg p-1 w-fit">
        <button
          @click="activeTab = 'steps'"
          :class="['px-4 py-2 rounded-md text-sm font-medium transition-colors', activeTab === 'steps' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700']"
        >
          Step Timeline
        </button>
        <button
          @click="activeTab = 'dag'"
          :class="['px-4 py-2 rounded-md text-sm font-medium transition-colors', activeTab === 'dag' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700']"
        >
          DAG View
        </button>
      </div>

      <!-- Steps timeline -->
      <div v-if="activeTab === 'steps'" class="card">
        <StepTimeline v-if="run.step_runs?.length" :steps="run.step_runs" />
        <p v-else class="text-sm text-gray-400 text-center py-8">No step execution data available</p>
      </div>

      <!-- DAG view with live statuses -->
      <div v-else class="card">
        <DagVisualization
          v-if="dagSteps.length"
          :steps="dagSteps"
          :step-statuses="stepStatuses"
          height="420px"
        />
        <p v-else class="text-sm text-gray-400 text-center py-8">No DAG definition available</p>
      </div>
    </template>
  </div>
</template>
