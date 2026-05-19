<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { healthApi } from '@/api/health'
import type { HealthMetrics } from '@/types'
import PageHeader from '@/components/ui/PageHeader.vue'
import MetricCard from '@/components/ui/MetricCard.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'

const metrics = ref<HealthMetrics | null>(null)
const loading = ref(true)
const error = ref('')
let refreshInterval: ReturnType<typeof setInterval> | null = null

async function loadMetrics() {
  try {
    metrics.value = await healthApi.getMetrics()
    error.value = ''
  } catch {
    error.value = 'Failed to load metrics'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadMetrics()
  refreshInterval = setInterval(loadMetrics, 15000) // Auto-refresh every 15s
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})

function getHealthColor(rate: number): 'success' | 'warning' | 'danger' {
  if (rate >= 95) return 'success'
  if (rate >= 80) return 'warning'
  return 'danger'
}
</script>

<template>
  <div>
    <PageHeader title="System Health" subtitle="Real-time platform performance metrics">
      <template #actions>
        <button @click="loadMetrics" class="btn-secondary btn-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
          </svg>
          Refresh
        </button>
      </template>
    </PageHeader>

    <LoadingSpinner v-if="loading && !metrics" text="Loading health metrics..." />

    <template v-else-if="metrics">
      <!-- Primary metrics -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <MetricCard
          label="Active Runs"
          :value="metrics.active_runs"
          subtitle="Currently executing"
          color="info"
        />
        <MetricCard
          label="Total (24h)"
          :value="metrics.last_24h.total_runs"
          subtitle="Workflows executed"
        />
        <MetricCard
          label="Success Rate"
          :value="`${metrics.last_24h.success_rate}%`"
          :color="getHealthColor(metrics.last_24h.success_rate)"
          :subtitle="`${metrics.last_24h.success_count} of ${metrics.last_24h.total_runs}`"
        />
        <MetricCard
          label="Avg Duration"
          :value="`${metrics.last_24h.avg_duration_seconds}s`"
          subtitle="Mean execution time"
        />
      </div>

      <!-- Detailed panels -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Status breakdown -->
        <div class="card lg:col-span-2">
          <h2 class="text-sm font-semibold text-gray-900 mb-4">Execution Breakdown (24h)</h2>

          <div v-if="metrics.last_24h.total_runs > 0">
            <!-- Progress bar -->
            <div class="h-4 bg-gray-100 rounded-full overflow-hidden flex mb-4">
              <div
                class="bg-emerald-500 h-full transition-all duration-500"
                :style="{ width: metrics.last_24h.success_rate + '%' }"
              ></div>
              <div
                class="bg-red-500 h-full transition-all duration-500"
                :style="{ width: metrics.last_24h.failure_rate + '%' }"
              ></div>
              <div
                v-if="metrics.last_24h.timeout_count > 0"
                class="bg-amber-500 h-full transition-all duration-500"
                :style="{ width: ((metrics.last_24h.timeout_count / metrics.last_24h.total_runs) * 100) + '%' }"
              ></div>
            </div>

            <!-- Legend -->
            <div class="grid grid-cols-3 gap-4">
              <div class="text-center p-3 rounded-lg bg-emerald-50">
                <p class="text-2xl font-bold text-emerald-700">{{ metrics.last_24h.success_count }}</p>
                <p class="text-xs text-emerald-600 font-medium">Succeeded</p>
              </div>
              <div class="text-center p-3 rounded-lg bg-red-50">
                <p class="text-2xl font-bold text-red-700">{{ metrics.last_24h.failed_count }}</p>
                <p class="text-xs text-red-600 font-medium">Failed</p>
              </div>
              <div class="text-center p-3 rounded-lg bg-amber-50">
                <p class="text-2xl font-bold text-amber-700">{{ metrics.last_24h.timeout_count }}</p>
                <p class="text-xs text-amber-600 font-medium">Timed Out</p>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8 text-sm text-gray-400">
            No workflow runs in the last 24 hours
          </div>
        </div>

        <!-- Service status -->
        <div class="card">
          <h2 class="text-sm font-semibold text-gray-900 mb-4">Service Health</h2>
          <div class="space-y-3">
            <div v-for="service in ['API Gateway', 'Identity', 'Workflow', 'Execution', 'AI']" :key="service"
              class="flex items-center justify-between py-2"
            >
              <span class="text-sm text-gray-700">{{ service }}</span>
              <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-xs text-emerald-700 font-medium">Healthy</span>
              </div>
            </div>
          </div>
          <div class="mt-4 pt-3 border-t border-gray-100">
            <p class="text-[10px] text-gray-400">
              Last updated: {{ new Date(metrics.calculated_at).toLocaleTimeString() }}
            </p>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
