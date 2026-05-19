<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const store = useWorkflowStore()
const auth = useAuthStore()
const search = ref('')

onMounted(() => {
  store.fetchWorkflows()
})

function handleSearch() {
  store.fetchWorkflows({ search: search.value })
}

function handlePageChange(page: number) {
  store.fetchWorkflows({ search: search.value, page })
}

async function handleTrigger(id: string) {
  try {
    const result = await store.triggerWorkflow(id)
    router.push(`/runs/${result.run_id}`)
  } catch {
    // Error handled in store
  }
}

async function handleDelete(id: string) {
  if (!confirm('Deactivate this workflow?')) return
  await store.deleteWorkflow(id)
}
</script>

<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold">Workflows</h1>
      <RouterLink v-if="auth.canWrite" to="/workflows/new" class="btn-primary">
        + New Workflow
      </RouterLink>
    </div>

    <!-- Search -->
    <div class="mb-4">
      <input
        v-model="search"
        @keyup.enter="handleSearch"
        type="text"
        class="input max-w-sm"
        placeholder="Search workflows..."
      />
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="text-center py-12 text-gray-500">Loading...</div>

    <!-- Error -->
    <div v-else-if="store.error" class="p-4 bg-red-50 text-red-700 rounded-lg">
      {{ store.error }}
    </div>

    <!-- List -->
    <div v-else-if="store.workflows.length > 0" class="space-y-3">
      <div
        v-for="workflow in store.workflows"
        :key="workflow.id"
        class="card flex items-center justify-between"
      >
        <RouterLink :to="`/workflows/${workflow.id}`" class="flex-1">
          <h3 class="font-medium">{{ workflow.name }}</h3>
          <p class="text-sm text-gray-500 mt-1">{{ workflow.description || 'No description' }}</p>
          <div class="flex gap-2 mt-2">
            <span :class="workflow.is_active ? 'badge-success' : 'badge-gray'">
              {{ workflow.is_active ? 'Active' : 'Inactive' }}
            </span>
            <span v-if="workflow.latest_version" class="badge-info">
              v{{ workflow.latest_version.version }}
            </span>
          </div>
        </RouterLink>

        <div v-if="auth.canWrite" class="flex gap-2 ml-4">
          <button @click="handleTrigger(workflow.id)" class="btn-success text-sm">
            ▶ Trigger
          </button>
          <RouterLink :to="`/workflows/${workflow.id}/edit`" class="btn-secondary text-sm">
            ✏️ Edit
          </RouterLink>
          <button @click="handleDelete(workflow.id)" class="btn-danger text-sm">
            🗑️
          </button>
        </div>
      </div>

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

    <!-- Empty state -->
    <div v-else class="text-center py-12">
      <p class="text-gray-500">No workflows found.</p>
      <RouterLink v-if="auth.canWrite" to="/workflows/new" class="btn-primary mt-4 inline-block">
        Create your first workflow
      </RouterLink>
    </div>
  </div>
</template>
