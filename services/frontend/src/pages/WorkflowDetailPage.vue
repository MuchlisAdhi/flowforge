<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
import { useAuthStore } from '@/stores/auth'
import { workflowsApi } from '@/api/workflows'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import DagVisualization from '@/components/DagVisualization.vue'
import { ref } from 'vue'

const route = useRoute()
const router = useRouter()
const store = useWorkflowStore()
const auth = useAuthStore()
const workflowId = route.params.id as string
const triggering = ref(false)

onMounted(() => {
  store.fetchWorkflow(workflowId)
})

async function handleTrigger() {
  if (triggering.value) return
  triggering.value = true
  try {
    const result = await store.triggerWorkflow(workflowId)
    router.push(`/runs/${result.run_id}`)
  } finally {
    triggering.value = false
  }
}

async function handleRollback(version: number) {
  if (!confirm(`Rollback to version ${version}? This creates a new version with the old definition.`)) return
  await workflowsApi.rollback(workflowId, version)
  store.fetchWorkflow(workflowId)
}

function formatDate(date: string): string {
  return new Date(date).toLocaleString()
}
</script>

<template>
  <div>
    <LoadingSpinner v-if="store.loading" text="Loading workflow..." />

    <template v-else-if="store.currentWorkflow">
      <PageHeader
        :title="store.currentWorkflow.name"
        :subtitle="store.currentWorkflow.description || 'No description'"
      >
        <template #actions>
          <StatusBadge :status="store.currentWorkflow.is_active ? 'active' : 'inactive'" />
          <template v-if="auth.canWrite">
            <button @click="handleTrigger" :disabled="triggering || !store.currentWorkflow.is_active" class="btn-success">
              <svg v-if="triggering" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              <span v-else>▶</span>
              {{ triggering ? 'Triggering...' : 'Trigger Run' }}
            </button>
            <RouterLink :to="`/workflows/${workflowId}/edit`" class="btn-secondary">Edit</RouterLink>
          </template>
        </template>
      </PageHeader>

      <!-- DAG Visualization -->
      <div class="card mb-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-sm font-semibold text-gray-900">Workflow DAG</h2>
          <span v-if="store.currentWorkflow.latest_version" class="text-xs text-gray-400 font-mono">
            v{{ store.currentWorkflow.latest_version.version }} ·
            timeout: {{ store.currentWorkflow.latest_version.timeout_seconds }}s
          </span>
        </div>
        <DagVisualization
          v-if="store.currentWorkflow.latest_version?.definition?.steps"
          :steps="store.currentWorkflow.latest_version.definition.steps"
          height="320px"
        />
        <p v-else class="text-sm text-gray-400 text-center py-8">No version available</p>
      </div>

      <!-- Version History -->
      <div class="card">
        <h2 class="text-sm font-semibold text-gray-900 mb-4">Version History</h2>
        <div
          v-if="(store.currentWorkflow as any).versions?.length"
          class="divide-y divide-gray-100"
        >
          <div
            v-for="(version, idx) in (store.currentWorkflow as any).versions"
            :key="version.id"
            class="flex items-center justify-between py-3"
          >
            <div class="flex items-center gap-3">
              <div :class="[
                'w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold',
                idx === 0 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'
              ]">
                v{{ version.version }}
              </div>
              <div>
                <p class="text-sm text-gray-900">{{ version.change_note || 'No description' }}</p>
                <p class="text-xs text-gray-400">{{ formatDate(version.created_at) }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="idx === 0" class="badge-info text-[10px]">Current</span>
              <button
                v-else-if="auth.canWrite"
                @click="handleRollback(version.version)"
                class="btn-ghost btn-sm text-indigo-600"
              >
                ↩ Rollback
              </button>
            </div>
          </div>
        </div>
        <p v-else class="text-sm text-gray-400 text-center py-4">No versions</p>
      </div>
    </template>
  </div>
</template>
