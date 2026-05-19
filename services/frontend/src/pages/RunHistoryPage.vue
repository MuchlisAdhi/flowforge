<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRunStore } from '@/stores/runs'

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
  if (!date) return '-'
  return new Date(date).toLocaleString()
}

function getDuration(run: { started_at: string | null; completed_at: string | null }): string {
  if (!run.started_at || !run.completed_at) return '-'
  const ms = new Date(run.completed_at).getTime() - new Date(run.started_at).getTime()
  if (ms < 1000) return `${ms}ms`
  return `${(ms / 1000).toFixed(1)}s`
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-6">Run History</h1>

    <!-- Filters -->
    <div class="flex gap-4 mb-4">
      <select v-model="statusFilter" @change="handleFilter" class="input max-w-[200px]">
        <option value="">All Statuses</option>
        <option value="pending">Pending</option>
        <option value="running">Running</option>
        <option value="success">Success</option>
        <option value="failed">Failed</option>
        <option value="timeout">Timeout</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="text-center py-12 text-gray-500">Loading...</div>

    <!-- Table -->
    <div v-else-if="store.runs.length > 0" class="overflow-x-auto">
      <table class="w-full border-collapse">
        <thead>
          <tr class="border-b text-left text-sm text-gray-500">
            <th class="p-3">Workflow</th>
            <th class="p-3">Status</th>
            <th class="p-3">Trigger</th>
            <th class="p-3">Started</th>
            <th class="p-3">Duration</th>
          </tr>
        </thead>
        <tbody>
          <RouterLink
            v-for="run in store.runs"
            :key="run.id"
            :to="`/runs/${run.id}`"
            custom
            v-slot="{ navigate }"
          >
            <tr @click="navigate" class="border-b hover:bg-gray-50 cursor-pointer">
              <td class="p-3 font-medium">{{ run.workflow?.name || 'Unknown' }}</td>
              <td class="p-3">
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
              </td>
              <td class="p-3 text-sm text-gray-500">{{ run.trigger_type }}</td>
              <td class="p-3 text-sm text-gray-500">{{ formatDate(run.started_at) }}</td>
              <td class="p-3 text-sm text-gray-500">{{ getDuration(run) }}</td>
            </tr>
          </RouterLink>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="store.meta.last_page > 1" class="flex justify-center gap-2 mt-6">
        <button
          v-for="page in store.meta.last_page"
          :key="page"
          @click="handlePageChange(page)"
          :class="[
            'px-3 py-1 rounded text-sm',
            page === store.meta.current_page
              ? 'bg-primary-600 text-white'
              : 'bg-gray-200 hover:bg-gray-300',
          ]"
        >
          {{ page }}
        </button>
      </div>
    </div>

    <div v-else class="text-center py-12 text-gray-500">
      No runs found. Trigger a workflow to see results here.
    </div>
  </div>
</template>
