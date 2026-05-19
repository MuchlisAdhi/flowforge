<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
import { useAuthStore } from '@/stores/auth'
import DagVisualization from '@/components/DagVisualization.vue'

const route = useRoute()
const router = useRouter()
const store = useWorkflowStore()
const auth = useAuthStore()

const workflowId = route.params.id as string

onMounted(() => {
  store.fetchWorkflow(workflowId)
})

async function handleTrigger() {
  try {
    const result = await store.triggerWorkflow(workflowId)
    router.push(`/runs/${result.run_id}`)
  } catch {
    // handled in store
  }
}

async function handleRollback(version: number) {
  if (!confirm(`Rollback to version ${version}?`)) return
  try {
    await import('@/api/workflows').then(({ workflowsApi }) =>
      workflowsApi.rollback(workflowId, version)
    )
    store.fetchWorkflow(workflowId)
  } catch {
    // handled in store
  }
}
</script>

<template>
  <div>
    <div v-if="store.loading" class="text-center py-12 text-gray-500">Loading...</div>

    <template v-else-if="store.currentWorkflow">
      <!-- Header -->
      <div class="flex justify-between items-start mb-6">
        <div>
          <h1 class="text-2xl font-bold">{{ store.currentWorkflow.name }}</h1>
          <p class="text-gray-500 mt-1">{{ store.currentWorkflow.description }}</p>
          <div class="flex gap-2 mt-2">
            <span :class="store.currentWorkflow.is_active ? 'badge-success' : 'badge-gray'">
              {{ store.currentWorkflow.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
        </div>
        <div v-if="auth.canWrite" class="flex gap-2">
          <button @click="handleTrigger" class="btn-success">▶ Trigger</button>
          <RouterLink :to="`/workflows/${workflowId}/edit`" class="btn-secondary">
            ✏️ Edit
          </RouterLink>
        </div>
      </div>

      <!-- DAG Visualization -->
      <div class="card mb-6">
        <h2 class="font-semibold mb-4">Workflow DAG</h2>
        <DagVisualization
          v-if="store.currentWorkflow.latest_version?.definition?.steps"
          :steps="store.currentWorkflow.latest_version.definition.steps"
        />
        <p v-else class="text-gray-400">No version available</p>
      </div>

      <!-- Version History -->
      <div class="card">
        <h2 class="font-semibold mb-4">Version History</h2>
        <div
          v-if="(store.currentWorkflow as any).versions?.length"
          class="space-y-3"
        >
          <div
            v-for="version in (store.currentWorkflow as any).versions"
            :key="version.id"
            class="flex items-center justify-between p-3 border rounded-lg"
          >
            <div>
              <span class="font-medium">Version {{ version.version }}</span>
              <span class="text-sm text-gray-500 ml-2">{{ version.change_note || 'No note' }}</span>
              <p class="text-xs text-gray-400">{{ new Date(version.created_at).toLocaleString() }}</p>
            </div>
            <button
              v-if="auth.canWrite && version.version !== (store.currentWorkflow as any).versions[0]?.version"
              @click="handleRollback(version.version)"
              class="btn-secondary text-sm"
            >
              ↩ Rollback
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
