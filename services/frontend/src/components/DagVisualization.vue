<script setup lang="ts">
import { computed } from 'vue'
import { VueFlow } from '@vueflow/core'
import '@vueflow/core/dist/style.css'
import '@vueflow/core/dist/theme-default.css'
import type { WorkflowStep } from '@/types'

const props = defineProps<{
  steps: WorkflowStep[]
  stepStatuses?: Record<string, string>
}>()

// Convert workflow steps to Vue Flow nodes and edges
const elements = computed(() => {
  const nodes = props.steps.map((step, index) => {
    const level = getStepLevel(step, props.steps)
    const siblings = props.steps.filter((s) => getStepLevel(s, props.steps) === level)
    const siblingIndex = siblings.indexOf(step)

    const status = props.stepStatuses?.[step.id]

    return {
      id: step.id,
      type: 'default',
      position: { x: siblingIndex * 220, y: level * 120 },
      data: { label: step.name },
      style: getNodeStyle(step.type, status),
      class: status === 'running' ? 'animate-pulse' : '',
    }
  })

  const edges = props.steps.flatMap((step) =>
    step.depends_on.map((dep) => ({
      id: `${dep}-${step.id}`,
      source: dep,
      target: step.id,
      animated: props.stepStatuses?.[step.id] === 'running',
      style: { stroke: '#6b7280' },
    }))
  )

  return { nodes, edges }
})

function getStepLevel(step: WorkflowStep, allSteps: WorkflowStep[]): number {
  if (step.depends_on.length === 0) return 0
  const depLevels = step.depends_on.map((depId) => {
    const dep = allSteps.find((s) => s.id === depId)
    return dep ? getStepLevel(dep, allSteps) : 0
  })
  return Math.max(...depLevels) + 1
}

function getNodeStyle(type: string, status?: string) {
  const baseStyle: Record<string, string> = {
    borderRadius: '8px',
    padding: '8px 16px',
    fontSize: '12px',
    fontWeight: '500',
    border: '2px solid',
  }

  // Status-based coloring
  if (status === 'success') {
    return { ...baseStyle, background: '#dcfce7', borderColor: '#16a34a', color: '#166534' }
  }
  if (status === 'failed') {
    return { ...baseStyle, background: '#fef2f2', borderColor: '#dc2626', color: '#991b1b' }
  }
  if (status === 'running') {
    return { ...baseStyle, background: '#dbeafe', borderColor: '#2563eb', color: '#1e40af' }
  }

  // Type-based coloring (default)
  const typeColors: Record<string, { background: string; borderColor: string; color: string }> = {
    http: { background: '#eff6ff', borderColor: '#3b82f6', color: '#1e40af' },
    script: { background: '#faf5ff', borderColor: '#8b5cf6', color: '#5b21b6' },
    delay: { background: '#fffbeb', borderColor: '#f59e0b', color: '#92400e' },
    condition: { background: '#ecfdf5', borderColor: '#10b981', color: '#065f46' },
  }

  return { ...baseStyle, ...(typeColors[type] || typeColors.http) }
}
</script>

<template>
  <div class="h-[400px] border rounded-lg bg-gray-50">
    <VueFlow
      v-if="elements.nodes.length > 0"
      :nodes="elements.nodes"
      :edges="elements.edges"
      :default-viewport="{ zoom: 0.9, x: 50, y: 30 }"
      fit-view-on-init
      class="rounded-lg"
    />
    <div v-else class="flex items-center justify-center h-full text-gray-400">
      No steps to visualize
    </div>
  </div>

  <!-- Legend -->
  <div class="flex gap-4 mt-3 text-xs text-gray-500">
    <span class="flex items-center gap-1">
      <span class="w-3 h-3 rounded bg-blue-100 border border-blue-500"></span> HTTP
    </span>
    <span class="flex items-center gap-1">
      <span class="w-3 h-3 rounded bg-purple-100 border border-purple-500"></span> Script
    </span>
    <span class="flex items-center gap-1">
      <span class="w-3 h-3 rounded bg-yellow-100 border border-yellow-500"></span> Delay
    </span>
    <span class="flex items-center gap-1">
      <span class="w-3 h-3 rounded bg-green-100 border border-green-500"></span> Condition
    </span>
  </div>
</template>
