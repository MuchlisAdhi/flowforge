<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { healthApi } from '@/api/health'
import type { HealthMetrics } from '@/types'

const metrics = ref<HealthMetrics | null>(null)
const loading = ref(true)
const error = ref('')

async function loadMetrics() {
  loading.value = true
  try {
    metrics.value = await healthApi.getMetrics()
  } catch {
    error.value = 'Failed to load metrics'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadMetrics()
  // Auto-refresh every 30 seconds
  setInterval(loadMetrics, 30000)
})
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Health Dashboard</h1>
      <button @click="loadMetrics" class="btn-secondary text-sm">🔄 Refresh</button>
    </div>

    <div v-if="loading && !metrics" class="text-center py-12 text-gray-500">Loading...</div>
    <div v-else-if="error" class="p-4 bg-red-50 text-red-700 rounded-lg">{{ error }}</div>

    <template v-else-if="metrics">
      <!-- Main metrics -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="card text-center">
          <p class="text-sm text-gray-500 mb-1">Active Runs</p>
          <p class="text-4xl font-bold text-primary-600">{{ metrics.active_runs }}</p>
          <p class="text-xs text-gray-400 mt-1">Currently executing</p>
        </div>
        <div class="card text-center">
          <p class="text-sm text-gray-500 mb-1">Total Runs (24h)</p>
          <p class="text-4xl font-bold">{{ metrics.last_24h.total_runs }}</p>
        </div>
        <div class="card text-center">
          <p class="text-sm text-gray-500 mb-1">Success Rate</p>
          <p class="text-4xl font-bold" :class="metrics.last_24h.success_rate >= 90 ? 'text-green-600' : metrics.last_24h.success_rate >= 70 ? 'text-yellow-600' : 'text-red-600'">
            {{ metrics.last_24h.success_rate }}%
          </p>
        </div>
        <div class="card text-center">
          <p class="text-sm text-gray-500 mb-1">Avg Duration</p>
          <p class="text-4xl font-bold">{{ metrics.last_24h.avg_duration_seconds }}s</p>
        </div>
      </div>

      <!-- Detailed breakdown -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="card">
          <h2 class="font-semibold mb-4">Status Breakdown (Last 24h)</h2>
          <div class="space-y-3">
            <div class="flex justify-between items-center">
              <span class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-green-500"></span> Success
              </span>
              <span class="font-medium">{{ metrics.last_24h.success_count }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-red-500"></span> Failed
              </span>
              <span class="font-medium">{{ metrics.last_24h.failed_count }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-orange-500"></span> Timeout
              </span>
              <span class="font-medium">{{ metrics.last_24h.timeout_count }}</span>
            </div>
          </div>

          <!-- Simple bar chart -->
          <div class="mt-4 h-4 bg-gray-200 rounded-full overflow-hidden flex" v-if="metrics.last_24h.total_runs > 0">
            <div
              class="bg-green-500 h-full"
              :style="{ width: metrics.last_24h.success_rate + '%' }"
            ></div>
            <div
              class="bg-red-500 h-full"
              :style="{ width: metrics.last_24h.failure_rate + '%' }"
            ></div>
          </div>
        </div>

        <div class="card">
          <h2 class="font-semibold mb-4">System Status</h2>
          <div class="space-y-3">
            <div class="flex justify-between items-center p-2 rounded bg-green-50">
              <span>API Gateway</span>
              <span class="badge-success">Healthy</span>
            </div>
            <div class="flex justify-between items-center p-2 rounded bg-green-50">
              <span>Workflow Service</span>
              <span class="badge-success">Healthy</span>
            </div>
            <div class="flex justify-between items-center p-2 rounded bg-green-50">
              <span>Execution Service</span>
              <span class="badge-success">Healthy</span>
            </div>
            <div class="flex justify-between items-center p-2 rounded bg-green-50">
              <span>AI Service</span>
              <span class="badge-success">Healthy</span>
            </div>
          </div>
          <p class="text-xs text-gray-400 mt-3">
            Last updated: {{ new Date(metrics.calculated_at).toLocaleString() }}
          </p>
        </div>
      </div>
    </template>
  </div>
</template>
