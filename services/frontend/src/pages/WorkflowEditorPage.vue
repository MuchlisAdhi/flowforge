<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
import { workflowsApi } from '@/api/workflows'
import PageHeader from '@/components/ui/PageHeader.vue'
import ErrorAlert from '@/components/ui/ErrorAlert.vue'
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue'
import DagVisualization from '@/components/DagVisualization.vue'
import type { WorkflowStep } from '@/types'

const route = useRoute()
const router = useRouter()
const store = useWorkflowStore()

const isEdit = !!route.params.id
const workflowId = route.params.id as string

const name = ref('')
const description = ref('')
const timeoutSeconds = ref(300)
const stepsJson = ref('')
const changeNote = ref('')
const error = ref('')
const saving = ref(false)
const loadingPage = ref(isEdit)
const previewSteps = ref<WorkflowStep[]>([])
const showPreview = ref(false)

const exampleSteps = JSON.stringify([
  {
    id: 'fetch_data',
    type: 'http',
    name: 'Fetch Data',
    depends_on: [],
    config: { method: 'GET', url: 'https://api.example.com/data', headers: {} },
    retry: { max_retries: 3, backoff: 'exponential', initial_delay_ms: 1000 },
  },
  {
    id: 'validate',
    type: 'condition',
    name: 'Validate Response',
    depends_on: ['fetch_data'],
    config: { expression: "previous.status_code == 200" },
  },
  {
    id: 'notify',
    type: 'http',
    name: 'Send Notification',
    depends_on: ['validate'],
    config: { method: 'POST', url: 'https://webhook.example.com/notify', headers: { 'Content-Type': 'application/json' } },
  },
], null, 2)

onMounted(async () => {
  if (isEdit) {
    await store.fetchWorkflow(workflowId)
    if (store.currentWorkflow) {
      name.value = store.currentWorkflow.name
      description.value = store.currentWorkflow.description || ''
      if (store.currentWorkflow.latest_version) {
        timeoutSeconds.value = store.currentWorkflow.latest_version.timeout_seconds
        stepsJson.value = JSON.stringify(store.currentWorkflow.latest_version.definition.steps, null, 2)
      }
    }
    loadingPage.value = false
  } else {
    stepsJson.value = exampleSteps
  }
})

function handlePreview() {
  try {
    previewSteps.value = JSON.parse(stepsJson.value)
    showPreview.value = true
    error.value = ''
  } catch {
    error.value = 'Invalid JSON — cannot preview DAG'
    showPreview.value = false
  }
}

async function handleSave() {
  error.value = ''
  saving.value = true

  let steps: WorkflowStep[]
  try {
    steps = JSON.parse(stepsJson.value)
  } catch {
    error.value = 'Invalid JSON in steps definition. Please check syntax.'
    saving.value = false
    return
  }

  try {
    if (isEdit) {
      await workflowsApi.update(workflowId, {
        name: name.value,
        description: description.value,
        timeout_seconds: timeoutSeconds.value,
        steps,
        change_note: changeNote.value,
      })
      router.push(`/workflows/${workflowId}`)
    } else {
      const workflow = await store.createWorkflow({
        name: name.value,
        description: description.value,
        timeout_seconds: timeoutSeconds.value,
        steps,
      })
      router.push(`/workflows/${workflow.id}`)
    }
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string; message?: string } } }
    error.value = err.response?.data?.error || err.response?.data?.message || 'Failed to save workflow'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <LoadingSpinner v-if="loadingPage" text="Loading workflow..." />

    <template v-else>
      <PageHeader :title="isEdit ? 'Edit Workflow' : 'Create Workflow'">
        <template #actions>
          <button @click="router.back()" class="btn-secondary">Cancel</button>
          <button @click="handlePreview" class="btn-secondary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            Preview DAG
          </button>
          <button @click="handleSave" :disabled="saving || !name" class="btn-primary">
            <svg v-if="saving" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ saving ? 'Saving...' : isEdit ? 'Update' : 'Create' }}
          </button>
        </template>
      </PageHeader>

      <ErrorAlert v-if="error" :message="error" dismissible @dismiss="error = ''" />

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left: Form -->
        <div class="space-y-5">
          <div class="card">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">Workflow Details</h2>
            <div class="space-y-4">
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Name *</label>
                <input v-model="name" type="text" class="input" required placeholder="e.g. Daily Student Sync" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-700 mb-1.5">Description</label>
                <textarea v-model="description" class="input resize-none" rows="2" placeholder="What does this workflow do?"></textarea>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-medium text-gray-700 mb-1.5">Timeout (seconds)</label>
                  <input v-model.number="timeoutSeconds" type="number" class="input" min="10" max="86400" />
                </div>
                <div v-if="isEdit">
                  <label class="block text-xs font-medium text-gray-700 mb-1.5">Change Note</label>
                  <input v-model="changeNote" type="text" class="input" placeholder="What changed?" />
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="flex items-center justify-between mb-3">
              <h2 class="text-sm font-semibold text-gray-900">Steps (JSON DAG) *</h2>
              <span class="text-[10px] text-gray-400">Supports: http, script, delay, condition</span>
            </div>
            <textarea
              v-model="stepsJson"
              class="input font-mono text-xs resize-none"
              rows="22"
              required
              spellcheck="false"
            ></textarea>
          </div>
        </div>

        <!-- Right: Preview -->
        <div>
          <div class="card sticky top-8">
            <h2 class="text-sm font-semibold text-gray-900 mb-4">DAG Preview</h2>
            <DagVisualization
              v-if="showPreview && previewSteps.length > 0"
              :steps="previewSteps"
              height="400px"
            />
            <div v-else class="flex items-center justify-center h-[400px] bg-gray-50 rounded-xl border-2 border-dashed border-gray-200">
              <div class="text-center">
                <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <p class="text-sm text-gray-400">Click "Preview DAG" to visualize</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
