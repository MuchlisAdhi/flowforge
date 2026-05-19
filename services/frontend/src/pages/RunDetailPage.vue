<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import { useRunStore } from '@/stores/runs'

const route = useRoute()
const store = useRunStore()
const runId = route.params.id as string

onMounted(() => {
  store.fetchRun(runId)
})

const run = computed(() => store.currentRun)

function statusColor(status: string): string {
  const colors: Record<string, string> = {
    pending: 'bg-gray-200 text-gray-700',
    running: 'bg-blue-100 text-blue-800 animate-pulse',
    success: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
    skipped: 'bg-gray-100 text-gray-600',
    timeout: 'bg-orange-100 text-orange-800',
  }
  return colors[status] || 'bg-gray-100 text-gray-700'
}

function formatDate(date: string | null): string {
  if (!date) return '-'
  return new Date(date).toLocaleString()
}
</script>

<template>
  <div>
    <div v-if="store.loading" class="text-center py-12 text-gray-500">Loading...</div>

    <template v-else-if="run">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center gap-3">
          <h1 class="text-2xl font-bold">Run Detail</h1>
          <span :class="['badge text-sm', statusColor(run.status)]">
            {{ run.status }}
          </span>
        </div>
        <p class="text-gray-500 text-sm mt-1">
          {{ run.workflow?.name || 'Workflow' }} · Triggered {{ formatDate(run.created_at) }}
        </p>
      </div>

      <!-- Run info -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="card">
          <p class="text-xs text-gray-500">Trigger Type</p>
          <p class="font-medium">{{ run.trigger_type }}</p>
        </div>
        <div class="card">
          <p class="text-xs text-gray-500">Started At</p>
          <p class="font-medium text-sm">{{ formatDate(run.started_at) }}</p>
        </div>
        <div class="card">
          <p class="text-xs text-gray-500">Completed At</p>
          <p class="font-medium text-sm">{{ formatDate(run.completed_at) }}</p>
        </div>
        <div class="card">
          <p class="text-xs text-gray-500">Error</p>
          <p class="font-medium text-sm text-red-600">{{ run.error_message || 'None' }}</p>
        </div>
      </div>

      <!-- Step runs -->
      <div class="card">
        <h2 class="font-semibold mb-4">Step Execution</h2>
        <div v-if="run.step_runs?.length" class="space-y-3">
          <div
            v-for="step in run.step_runs"
            :key="step.id"
            class="border rounded-lg p-4 transition-all"
            :class="{ 'border-blue-300 shadow-sm': step.status === 'running' }"
          >
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span :class="['w-3 h-3 rounded-full', {
                  'bg-gray-300': step.status === 'pending',
                  'bg-blue-500 animate-pulse': step.status === 'running',
                  'bg-green-500': step.status === 'success',
                  'bg-red-500': step.status === 'failed',
                  'bg-gray-400': step.status === 'skipped',
                }]"></span>
                <div>
                  <span class="font-medium">{{ step.step_name }}</span>
                  <span class="text-xs text-gray-400 ml-2">({{ step.step_type }})</span>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <span v-if="step.attempt > 1" class="text-xs text-gray-400">
                  Attempt {{ step.attempt }}
                </span>
                <span :class="['badge text-xs', statusColor(step.status)]">
                  {{ step.status }}
                </span>
              </div>
            </div>

            <!-- Step details (expandable) -->
            <div v-if="step.error_message" class="mt-2 text-sm text-red-600 bg-red-50 p-2 rounded">
              {{ step.error_message }}
            </div>
            <div v-if="step.output" class="mt-2 text-xs bg-gray-50 p-2 rounded font-mono overflow-x-auto">
              {{ JSON.stringify(step.output, null, 2) }}
            </div>
          </div>
        </div>
        <p v-else class="text-gray-400 text-sm">No step data available</p>
      </div>
    </template>
  </div>
</template>
