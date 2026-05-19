<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { healthApi } from '@/api/health'
import type { HealthMetrics } from '@/types'
import { useWorkflowStore } from '@/stores/workflows'
import { useRunStore } from '@/stores/runs'
import MetricCard from '@/components/ui/MetricCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import PageHeader from '@/components/ui/PageHeader.vue'

const metrics = ref<HealthMetrics | null>(null)
const loading = ref(true)
const workflowStore = useWorkflowStore()
const runStore = useRunStore()

onMounted(async () => {
  try {
    const [metricsData] = await Promise.all([
      healthApi.getMetrics(),
      workflowStore.fetchWorkflows({ per_page: 5 }),
      runStore.fetchRuns({ per_page: 8 }),
    ])
    metrics.value = metricsData
  } finally {
    loading.value = false
  }
})

function formatTime(date: string | null): string {
  if (!date) return '—'
  const d = new Date(date)
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function timeAgo(date: string): string {
  const seconds = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
  if (seconds < 60) return 'just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  return `${Math.floor(seconds / 86400)}d ago`
}
</script>

<template>
  <div>
    <PageHeader title="Dashboard" subtitle="Real-time workflow execution overview" />

    <LoadingSpinner v-if="loading" text="Loading metrics..." />

    <template v-else-if="metrics">
      <!-- Metrics grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <MetricCard
          label="Active Runs"
          :value="metrics.active_runs"
          subtitle="Currently executing"
          color="info"
        />
        <MetricCard
          label="Total Runs (24h)"
          :value="metrics.last_24h.total_runs"
          subtitle="Last 24 hours"
        />
        <MetricCard
          label="Success Rate"
          :value="`${metrics.last_24h.success_rate}%`"
          :color="metrics.last_24h.success_rate >= 90 ? 'success' : metrics.last_24h.success_rate >= 70 ? 'warning' : 'danger'"
          :subtitle="`${metrics.last_24h.success_count} succeeded`"
        />
        <MetricCard
          label="Avg Duration"
          :value="`${metrics.last_24h.avg_duration_seconds}s`"
          subtitle="Mean execution time"
        />
      </div>

      <!-- Success/Failure bar -->
      <div v-if="metrics.last_24h.total_runs > 0" class="card-compact mb-8">
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-medium text-gray-600">Execution Results (24h)</span>
          <div class="flex gap-4 text-xs text-gray-500">
            <span class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
              {{ metrics.last_24h.success_count }} passed
            </span>
            <span class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-red-500"></span>
              {{ metrics.last_24h.failed_count }} failed
            </span>
            <span class="flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-amber-500"></span>
              {{ metrics.last_24h.timeout_count }} timeout
            </span>
          </div>
        </div>
        <div class="h-3 bg-gray-100 rounded-full overflow-hidden flex">
          <div class="bg-emerald-500 h-full transition-all" :style="{ width: metrics.last_24h.success_rate + '%' }"></div>
          <div class="bg-red-500 h-full transition-all" :style="{ width: metrics.last_24h.failure_rate + '%' }"></div>
        </div>
      </div>

      <!-- Two columns: Workflows + Recent Runs -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Workflows -->
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900">Workflows</h2>
            <RouterLink to="/workflows" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
              View all →
            </RouterLink>
          </div>
          <div v-if="workflowStore.workflows.length === 0" class="text-sm text-gray-400 text-center py-6">
            No workflows created yet
          </div>
          <div v-else class="divide-y divide-gray-100">
            <RouterLink
              v-for="wf in workflowStore.workflows"
              :key="wf.id"
              :to="`/workflows/${wf.id}`"
              class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded-lg transition-colors"
            >
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate">{{ wf.name }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ timeAgo(wf.updated_at) }}</p>
              </div>
              <StatusBadge :status="wf.is_active ? 'active' : 'inactive'" size="sm" />
            </RouterLink>
          </div>
        </div>

        <!-- Recent Runs -->
        <div class="card">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900">Recent Runs</h2>
            <RouterLink to="/runs" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
              View all →
            </RouterLink>
          </div>
          <div v-if="runStore.runs.length === 0" class="text-sm text-gray-400 text-center py-6">
            No runs yet — trigger a workflow
          </div>
          <div v-else class="divide-y divide-gray-100">
            <RouterLink
              v-for="run in runStore.runs"
              :key="run.id"
              :to="`/runs/${run.id}`"
              class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded-lg transition-colors"
            >
              <div class="min-w-0 flex items-center gap-3">
                <div
                  :class="[
                    'w-2 h-2 rounded-full flex-shrink-0',
                    run.status === 'success' ? 'bg-emerald-500' :
                    run.status === 'failed' || run.status === 'timeout' ? 'bg-red-500' :
                    run.status === 'running' ? 'bg-indigo-500 animate-pulse' :
                    'bg-gray-300'
                  ]"
                ></div>
                <div class="min-w-0">
                  <p class="text-sm text-gray-900 truncate">{{ run.workflow?.name || 'Workflow' }}</p>
                  <p class="text-xs text-gray-400">{{ formatTime(run.started_at) }}</p>
                </div>
              </div>
              <StatusBadge :status="run.status" size="sm" :pulse="run.status === 'running'" />
            </RouterLink>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
