<script setup lang="ts">
import { computed } from 'vue'
import { VueFlow, type Node, type Edge } from '@vue-flow/core'
import '@vue-flow/core/dist/style.css'
import '@vue-flow/core/dist/theme-default.css'
import type { WorkflowStep } from '@/types'

const props = defineProps<{
  steps: WorkflowStep[]
  stepStatuses?: Record<string, string>
  height?: string
}>()

const flowHeight = computed(() => props.height || '360px')

// Layout algorithm: compute level per node, then position within level
function computeLevel(step: WorkflowStep, allSteps: WorkflowStep[], memo: Map<string, number>): number {
  if (memo.has(step.id)) return memo.get(step.id)!
  if (step.depends_on.length === 0) {
    memo.set(step.id, 0)
    return 0
  }
  const depLevels = step.depends_on.map((depId) => {
    const dep = allSteps.find((s) => s.id === depId)
    return dep ? computeLevel(dep, allSteps, memo) : 0
  })
  const level = Math.max(...depLevels) + 1
  memo.set(step.id, level)
  return level
}

const { nodes, edges } = computed(() => {
  const memo = new Map<string, number>()
  const levelGroups = new Map<number, WorkflowStep[]>()

  // Compute levels
  for (const step of props.steps) {
    const level = computeLevel(step, props.steps, memo)
    if (!levelGroups.has(level)) levelGroups.set(level, [])
    levelGroups.get(level)!.push(step)
  }

  // Position nodes
  const nodeSpacingX = 240
  const nodeSpacingY = 100
  const nodeList: Node[] = []

  for (const [level, stepsInLevel] of levelGroups) {
    const totalWidth = (stepsInLevel.length - 1) * nodeSpacingX
    const startX = -totalWidth / 2

    stepsInLevel.forEach((step, idx) => {
      const status = props.stepStatuses?.[step.id]
      nodeList.push({
        id: step.id,
        position: { x: startX + idx * nodeSpacingX, y: level * nodeSpacingY },
        data: {
          label: step.name,
          type: step.type,
          status,
        },
        style: getNodeStyle(step.type, status),
      })
    })
  }

  // Build edges
  const edgeList: Edge[] = props.steps.flatMap((step) =>
    step.depends_on.map((dep) => ({
      id: `e-${dep}-${step.id}`,
      source: dep,
      target: step.id,
      animated: props.stepStatuses?.[step.id] === 'running',
      style: getEdgeStyle(props.stepStatuses?.[step.id]),
      type: 'smoothstep',
    }))
  )

  return { nodes: nodeList, edges: edgeList }
}).value

function getEdgeStyle(targetStatus?: string): Record<string, string> {
  if (targetStatus === 'running') return { stroke: '#6366f1', strokeWidth: '2' }
  if (targetStatus === 'success') return { stroke: '#10b981', strokeWidth: '2' }
  if (targetStatus === 'failed') return { stroke: '#ef4444', strokeWidth: '2' }
  return { stroke: '#94a3b8', strokeWidth: '1.5' }
}

function getNodeStyle(type: string, status?: string): Record<string, string> {
  const base: Record<string, string> = {
    borderRadius: '10px',
    padding: '10px 18px',
    fontSize: '12px',
    fontWeight: '600',
    border: '2px solid',
    boxShadow: '0 1px 3px 0 rgb(0 0 0 / 0.05)',
    minWidth: '140px',
    textAlign: 'center',
  }

  // Status-first coloring (takes precedence over type)
  if (status === 'success') return { ...base, background: '#ecfdf5', borderColor: '#10b981', color: '#065f46' }
  if (status === 'failed') return { ...base, background: '#fef2f2', borderColor: '#ef4444', color: '#991b1b' }
  if (status === 'running') return { ...base, background: '#eef2ff', borderColor: '#6366f1', color: '#3730a3' }
  if (status === 'pending') return { ...base, background: '#f9fafb', borderColor: '#d1d5db', color: '#6b7280' }

  // Type-based coloring (default — no execution status)
  const typeStyles: Record<string, { background: string; borderColor: string; color: string }> = {
    http: { background: '#eff6ff', borderColor: '#3b82f6', color: '#1e40af' },
    script: { background: '#faf5ff', borderColor: '#8b5cf6', color: '#5b21b6' },
    delay: { background: '#fffbeb', borderColor: '#f59e0b', color: '#92400e' },
    condition: { background: '#ecfdf5', borderColor: '#10b981', color: '#065f46' },
  }

  const typeStyle = typeStyles[type] || typeStyles.http
  return { ...base, ...typeStyle }
}
</script>

<template>
  <div>
    <div :style="{ height: flowHeight }" class="border border-gray-200 rounded-xl bg-gray-50/50 overflow-hidden">
      <VueFlow
        v-if="nodes.length > 0"
        :nodes="nodes"
        :edges="edges"
        :default-viewport="{ zoom: 0.85, x: 200, y: 20 }"
        fit-view-on-init
        :min-zoom="0.3"
        :max-zoom="1.5"
      />
      <div v-else class="flex items-center justify-center h-full text-gray-400 text-sm">
        No steps to visualize
      </div>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-4 mt-3 px-1">
      <span class="flex items-center gap-1.5 text-xs text-gray-500">
        <span class="w-3 h-3 rounded-sm bg-blue-100 border-2 border-blue-400"></span> HTTP
      </span>
      <span class="flex items-center gap-1.5 text-xs text-gray-500">
        <span class="w-3 h-3 rounded-sm bg-purple-100 border-2 border-purple-400"></span> Script
      </span>
      <span class="flex items-center gap-1.5 text-xs text-gray-500">
        <span class="w-3 h-3 rounded-sm bg-amber-100 border-2 border-amber-400"></span> Delay
      </span>
      <span class="flex items-center gap-1.5 text-xs text-gray-500">
        <span class="w-3 h-3 rounded-sm bg-emerald-100 border-2 border-emerald-400"></span> Condition
      </span>
      <span v-if="stepStatuses" class="flex items-center gap-1.5 text-xs text-gray-500 ml-4">
        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span> Running
      </span>
    </div>
  </div>
</template>
