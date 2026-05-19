<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
import { useAuthStore } from '@/stores/auth'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import ErrorAlert from '@/components/ui/ErrorAlert.vue'

const router = useRouter()
const store = useWorkflowStore()
const auth = useAuthStore()
const search = ref('')
const triggeringId = ref<string | null>(null)
const triggerSuccess = ref<string | null>(null)

onMounted(() => {
  store.fetchWorkflows()
})

function handleSearch() {
  store.fetchWorkflows({ search: search.value })
}

function handlePageChange(page: number) {
  store.fetchWorkflows({ search: search.value, page })
}

async function handleTrigger(id: string, event: Event) {
  event.preventDefault()
  event.stopPropagation()
  if (triggeringId.value) return

  triggeringId.value = id
  triggerSuccess.value = null

  try {
    const result = await store.triggerWorkflow(id)
    // Optimistic feedback — show success briefly then navigate
    triggerSuccess.value = id
    setTimeout(() => {
      router.push(`/runs/${result.run_id}`)
    }, 600)
  } catch {
    // Error handled in store
  } finally {
    triggeringId.value = null
  }
}

async function handleDelete(id: string, event: Event) {
  event.preventDefault()
  event.stopPropagation()
  if (!confirm('Deactivate this workflow? It can be reactivated later.')) return
  await store.deleteWorkflow(id)
}

function timeAgo(date: string): string {
  const seconds = Math.floor((Date.now() - new Date(date).getTime()) / 1000)
  if (seconds < 60) return 'just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`
  return new Date(date).toLocaleDateString()
}
</script>

<template>
  <div>
    <PageHeader title="Workflows" subtitle="Manage your workflow definitions">
      <template #actions>
        <RouterLink v-if="auth.canWrite" to="/workflows/new" class="btn-primary">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
          </svg>
          New Workflow
        </RouterLink>
      </template>
    </PageHeader>

    <!-- Search bar -->
    <div class="mb-6">
      <div class="relative max-w-md">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="search"
          @keyup.enter="handleSearch"
          type="text"
          class="input pl-10"
          placeholder="Search workflows..."
        />
      </div>
    </div>

    <!-- Error -->
    <ErrorAlert v-if="store.error" :message="store.error" dismissible @dismiss="store.error = null" />

    <!-- Loading -->
    <LoadingSpinner v-if="store.loading && store.workflows.length === 0" text="Loading workflows..." />

    <!-- Workflow cards -->
    <div v-else-if="store.workflows.length > 0" class="space-y-3">
      <RouterLink
        v-for="workflow in store.workflows"
        :key="workflow.id"
        :to="`/workflows/${workflow.id}`"
        :class="[
          'card-compact flex items-center gap-4 hover:border-indigo-200 hover:shadow transition-all group',
          triggerSuccess === workflow.id ? 'ring-2 ring-emerald-500 border-emerald-200' : ''
        ]"
      >
        <!-- Icon -->
        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center flex-shrink-0 group-hover:bg-indigo-100 transition-colors">
          <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-gray-900 truncate">{{ workflow.name }}</h3>
            <StatusBadge :status="workflow.is_active ? 'active' : 'inactive'" size="sm" />
            <span v-if="workflow.latest_version" class="text-[10px] text-gray-400 font-mono">
              v{{ workflow.latest_version.version }}
            </span>
          </div>
          <p class="text-xs text-gray-500 mt-0.5 truncate">
            {{ workflow.description || 'No description' }}
          </p>
          <p class="text-[10px] text-gray-400 mt-1">Updated {{ timeAgo(workflow.updated_at) }}</p>
        </div>

        <!-- Actions -->
        <div v-if="auth.canWrite" class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            @click="handleTrigger(workflow.id, $event)"
            :disabled="triggeringId === workflow.id || !workflow.is_active"
            :class="[
              'btn-success btn-sm',
              triggerSuccess === workflow.id ? 'bg-emerald-500' : '',
              triggeringId === workflow.id ? 'animate-pulse' : ''
            ]"
          >
            <svg v-if="triggeringId === workflow.id" class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span v-else-if="triggerSuccess === workflow.id">✓</span>
            <span v-else>▶</span>
            {{ triggerSuccess === workflow.id ? 'Triggered' : triggeringId === workflow.id ? '' : 'Run' }}
          </button>
          <RouterLink
            :to="`/workflows/${workflow.id}/edit`"
            class="btn-secondary btn-sm"
            @click.stop
          >
            Edit
          </RouterLink>
          <button @click="handleDelete(workflow.id, $event)" class="btn-ghost btn-sm text-red-500 hover:text-red-700 hover:bg-red-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
            </svg>
          </button>
        </div>
      </RouterLink>

      <!-- Pagination -->
      <div v-if="store.meta.last_page > 1" class="flex items-center justify-center gap-1 pt-4">
        <button
          v-for="page in store.meta.last_page"
          :key="page"
          @click="handlePageChange(page)"
          :class="[
            'w-8 h-8 rounded-lg text-sm font-medium transition-colors',
            page === store.meta.current_page
              ? 'bg-indigo-600 text-white'
              : 'text-gray-600 hover:bg-gray-100'
          ]"
        >
          {{ page }}
        </button>
      </div>
    </div>

    <!-- Empty state -->
    <EmptyState
      v-else
      icon="⚡"
      title="No workflows yet"
      description="Create your first automated workflow to get started."
    >
      <RouterLink v-if="auth.canWrite" to="/workflows/new" class="btn-primary">
        Create Workflow
      </RouterLink>
    </EmptyState>
  </div>
</template>
