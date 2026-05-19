<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRunStore } from '@/stores/runs'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const store = useRunStore()
const statusFilter = ref('')

onMounted(() => {
  store.fetchRuns()
})

function handleFilter() {
  store.fetchRuns({ status: statusFilter.value || undefined })
}

function handlePageChange(page: number) {
  store.fetchRuns({ status: statusFilter.value || undefined, page })
}

function formatDate(date: string | null): string {
  if (!date) return '—'
  return new Date(date).toLocaleString()
}

function getDuration(run: { started_at: string | null; completed_at: string | null }): string {
  if (!run.started_at || !run.completed_at) return '—'
  const ms = new Date(run.completed_at).getTime() - new Date(run.started_at).getTime()
  if (ms < 1000) return `${ms}ms`
  if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`
  return `${(ms / 60000).toFixed(1)}m`
}

const statuses = ['pending', 'running', 'success', 'failed', 'timeout', 'cancelled']
</script>

<template>
  <div>
    <PageHeader title="Run History" subtitle="Monitor workflow execution results" />

    <!-- Filters -->
    <div class="flex items-center gap-3 mb-6">
      <button
        @click="statusFilter = ''; handleFilter()"
        :class="['btn-sm', !statusFilter ? 'btn-primary' : 'btn-secondary']"
      >
        All
      </button>
      <button
        v-for="s in statuses"
        :key="s"
        @click="statusFilter = s; handleFilter()"
        :class="['btn-sm capitalize', statusFilter === s ? 'btn-primary' : 'btn-secondary']"
      >
        {{ s }}
      </button>
    </div>

    <LoadingSpinner v-if="store.loading && store.runs.length === 0" text="Loading runs..." />

    <!-- Table -->
    <div v-else-if="store.runs.length > 0" class="card overflow-hidden p-0">
      <table class="w-full">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-6 py-3">Workflow</th>
            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-6 py-3">Status</th>
            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-6 py-3">Trigger</th>
            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-6 py-3">Started</th>
            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider px-6 py-3">Duration</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <RouterLink
            v-for="run in store.runs"
            :key="run.id"
            :to="`/runs/${run.id}`"
            custom
            v-slot="{ navigate }"
          >
            <tr @click="navigate" class="hover:bg-gray-50 cursor-pointer transition-colors">
              <td class="px-6 py-4">
                <p class="text-sm font-medium text-gray-900">{{ run.workflow?.name || 'Unknown' }}</p>
                <p class="text-xs text-gray-400 font-mono mt-0.5">{{ run.id.slice(0, 8) }}...</p>
              </td>
              <td class="px-6 py-4">
                <StatusBadge :status="run.status" :pulse="run.status === 'running'" />
              </td>
              <td class="px-6 py-4">
                <span class="text-xs text-gray-600 capitalize">{{ run.trigger_type }}</span>
              </td>
              <td class="px-6 py-4">
                <span class="text-xs text-gray-600">{{ formatDate(run.started_at) }}</span>
              </td>
              <td class="px-6 py-4">
                <span class="text-xs font-mono text-gray-600">{{ getDuration(run) }}</span>
              </td>
            </tr>
          </RouterLink>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="store.meta.last_page > 1" class="flex items-center justify-between px-6 py-3 border-t border-gray-200 bg-gray-50">
        <p class="text-xs text-gray-500">
          Showing {{ (store.meta.current_page - 1) * store.meta.per_page + 1 }}-{{ Math.min(store.meta.current_page * store.meta.per_page, store.meta.total) }} of {{ store.meta.total }}
        </p>
        <div class="flex gap-1">
          <button
            v-for="page in store.meta.last_page"
            :key="page"
            @click="handlePageChange(page)"
            :class="[
              'w-8 h-8 rounded text-xs font-medium transition-colors',
              page === store.meta.current_page
                ? 'bg-indigo-600 text-white'
                : 'text-gray-600 hover:bg-gray-200'
            ]"
          >
            {{ page }}
          </button>
        </div>
      </div>
    </div>

    <EmptyState v-else icon="▶️" title="No runs found" description="Trigger a workflow to see execution history here." />
  </div>
</template>
