<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { healthApi } from '@/api/health'
import type { HealthMetrics } from '@/types'
import { useWorkflowStore } from '@/stores/workflows'
import { useRunStore } from '@/stores/runs'

const metrics = ref<HealthMetrics | null>(null)
const loading = ref(true)
const workflowStore = useWorkflowStore()
const runStore = useRunStore()

onMounted(async () => {
  try {
    const [metricsData] = await Promise.all([
      healthApi.getMetrics(),
      workflowStore.fetchWorkflows({ per_page: 5 }),
      runStore.fetchRuns({ per_page: 5 }),
    ])
    metrics.value = metricsData
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

    <!-- Loading state -->
    <div v-if="loading" class="flex items-center justify-center h-64">
      <p class="text-gray-500">Loading metrics...</p>
    </div>

    <template v-else-if="metrics">
      <!-- Metrics cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="card">
          <p class="text-sm text-gray-500">Active Runs</p>
          <p class="text-3xl font-bold text-primary-600">{{ metrics.active_runs }}</p>
        </div>
        <div class="card">
          <p class="text-sm text-gray-500">Total Runs (24h)</p>
          <p class="text-3xl font-bold">{{ metrics.last_24h.total_runs }}</p>
        </div>
        <div class="card">
          <p class="text-sm text-gray-500">Success Rate</p>
          <p class="text-3xl font-bold text-green-600">{{ metrics.last_24h.success_rate }}%</p>
        </div>
        <div class="card">
          <p class="text-sm text-gray-500">Avg Duration</p>
          <p class="text-3xl font-bold">{{ metrics.last_24h.avg_duration_seconds }}s</p>
        </div>
      </div>

      <!-- Status breakdown -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent workflows -->
        <div class="card">
          <h2 class="font-semibold mb-4">Recent Workflows</h2>
          <div v-if="workflowStore.workflows.length === 0" class="text-gray-400 text-sm">
            No workflows yet. Create your first one!
          </div>
          <div v-else class="space-y-3">
            <RouterLink
              v-for="wf in workflowStore.workflows"
              :key="wf.id"
              :to="`/workflows/${wf.id}`"
              class="block p-3 rounded-lg border hover:border-primary-300 transition-colors"
            >
              <div class="flex justify-between items-center">
                <span class="font-medium text-sm">{{ wf.name }}</span>
                <span :class="wf.is_active ? 'badge-success' : 'badge-gray'">
                  {{ wf.is_active ? 'Active' : 'Inactive' }}
                </span>
              </div>
            </RouterLink>
          </div>
        </div>

        <!-- Recent runs -->
        <div class="card">
          <h2 class="font-semibold mb-4">Recent Runs</h2>
          <div v-if="runStore.runs.length === 0" class="text-gray-400 text-sm">
            No runs yet. Trigger a workflow!
          </div>
          <div v-else class="space-y-3">
            <RouterLink
              v-for="run in runStore.runs"
              :key="run.id"
              :to="`/runs/${run.id}`"
              class="block p-3 rounded-lg border hover:border-primary-300 transition-colors"
            >
              <div class="flex justify-between items-center">
                <span class="text-sm">{{ run.workflow?.name || 'Workflow' }}</span>
                <span
                  :class="{
                    'badge-success': run.status === 'success',
                    'badge-danger': run.status === 'failed' || run.status === 'timeout',
                    'badge-warning': run.status === 'running',
                    'badge-info': run.status === 'pending',
                    'badge-gray': run.status === 'cancelled',
                  }"
                >
                  {{ run.status }}
                </span>
              </div>
            </RouterLink>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
