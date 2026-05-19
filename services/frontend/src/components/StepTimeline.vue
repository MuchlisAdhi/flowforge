<script setup lang="ts">
import { computed } from 'vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import type { StepRun } from '@/types'

const props = defineProps<{
  steps: StepRun[]
}>()

const sortedSteps = computed(() =>
  [...props.steps].sort((a, b) => {
    if (!a.started_at && !b.started_at) return 0
    if (!a.started_at) return 1
    if (!b.started_at) return -1
    return new Date(a.started_at).getTime() - new Date(b.started_at).getTime()
  })
)

function formatDuration(step: StepRun): string {
  if (!step.started_at || !step.completed_at) return '—'
  const ms = new Date(step.completed_at).getTime() - new Date(step.started_at).getTime()
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(1)}s`
}

function getStepIcon(type: string): string {
  const icons: Record<string, string> = {
    http: '🌐',
    script: '📜',
    delay: '⏱️',
    condition: '🔀',
  }
  return icons[type] || '⚙️'
}

function getTimelineColor(status: string): string {
  const colors: Record<string, string> = {
    success: 'bg-emerald-500',
    failed: 'bg-red-500',
    running: 'bg-indigo-500 animate-pulse',
    pending: 'bg-gray-300',
    skipped: 'bg-gray-400',
  }
  return colors[status] || 'bg-gray-300'
}
</script>

<template>
  <div class="flow-root">
    <ul class="-mb-8">
      <li v-for="(step, idx) in sortedSteps" :key="step.id" class="relative pb-8">
        <!-- Connector line -->
        <span
          v-if="idx !== sortedSteps.length - 1"
          class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200"
        ></span>

        <div class="relative flex items-start gap-4">
          <!-- Timeline dot -->
          <div class="relative flex h-8 w-8 flex-none items-center justify-center">
            <div
              :class="['h-3 w-3 rounded-full ring-4 ring-white', getTimelineColor(step.status)]"
            ></div>
          </div>

          <!-- Step content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <span class="text-base">{{ getStepIcon(step.step_type) }}</span>
                <span class="text-sm font-semibold text-gray-900">{{ step.step_name }}</span>
                <span class="text-xs text-gray-400">({{ step.step_type }})</span>
              </div>
              <div class="flex items-center gap-2">
                <span v-if="step.attempt > 1" class="text-xs text-gray-400">
                  attempt {{ step.attempt }}
                </span>
                <span class="text-xs text-gray-500 font-mono">{{ formatDuration(step) }}</span>
                <StatusBadge :status="step.status" size="sm" :pulse="step.status === 'running'" />
              </div>
            </div>

            <!-- Error message -->
            <div
              v-if="step.error_message"
              class="mt-2 text-xs bg-red-50 border border-red-100 rounded-lg p-3 text-red-700 font-mono"
            >
              {{ step.error_message }}
            </div>

            <!-- Output (collapsible) -->
            <details v-if="step.output && step.status === 'success'" class="mt-2">
              <summary class="text-xs text-gray-500 cursor-pointer hover:text-gray-700">
                View output
              </summary>
              <pre class="mt-1 text-xs bg-gray-50 border rounded-lg p-3 overflow-x-auto text-gray-700 max-h-32">{{ JSON.stringify(step.output, null, 2) }}</pre>
            </details>
          </div>
        </div>
      </li>
    </ul>
  </div>
</template>
