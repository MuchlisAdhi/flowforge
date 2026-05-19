<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useWorkflowStore } from '@/stores/workflows'
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
], null, 2)

onMounted(async () => {
  if (isEdit) {
    await store.fetchWorkflow(workflowId)
    if (store.currentWorkflow) {
      name.value = store.currentWorkflow.name
      description.value = store.currentWorkflow.description || ''
      if (store.currentWorkflow.latest_version) {
        timeoutSeconds.value = store.currentWorkflow.latest_version.timeout_seconds
        stepsJson.value = JSON.stringify(
          store.currentWorkflow.latest_version.definition.steps,
          null,
          2
        )
      }
    }
  } else {
    stepsJson.value = exampleSteps
  }
})

async function handleSave() {
  error.value = ''
  saving.value = true

  let steps: WorkflowStep[]
  try {
    steps = JSON.parse(stepsJson.value)
  } catch {
    error.value = 'Invalid JSON in steps definition'
    saving.value = false
    return
  }

  try {
    if (isEdit) {
      await import('@/api/workflows').then(({ workflowsApi }) =>
        workflowsApi.update(workflowId, {
          name: name.value,
          description: description.value,
          timeout_seconds: timeoutSeconds.value,
          steps,
          change_note: changeNote.value,
        })
      )
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
    <h1 class="text-2xl font-bold mb-6">
      {{ isEdit ? 'Edit Workflow' : 'Create Workflow' }}
    </h1>

    <form @submit.prevent="handleSave" class="space-y-6 max-w-4xl">
      <div v-if="error" class="p-3 bg-red-50 text-red-700 rounded-lg text-sm">
        {{ error }}
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
          <input v-model="name" type="text" class="input" required placeholder="My Workflow" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Timeout (seconds)</label>
          <input v-model.number="timeoutSeconds" type="number" class="input" min="10" max="86400" />
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
        <textarea v-model="description" class="input" rows="2" placeholder="What does this workflow do?"></textarea>
      </div>

      <div v-if="isEdit">
        <label class="block text-sm font-medium text-gray-700 mb-1">Change Note</label>
        <input v-model="changeNote" type="text" class="input" placeholder="What changed in this version?" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
          Steps (JSON DAG Definition) *
        </label>
        <textarea
          v-model="stepsJson"
          class="input font-mono text-sm"
          rows="20"
          required
          placeholder="Enter steps JSON..."
        ></textarea>
        <p class="text-xs text-gray-400 mt-1">
          Each step needs: id, type (http/script/delay/condition), name, depends_on, config
        </p>
      </div>

      <div class="flex gap-3">
        <button type="submit" class="btn-primary" :disabled="saving">
          {{ saving ? 'Saving...' : isEdit ? 'Update Workflow' : 'Create Workflow' }}
        </button>
        <button type="button" @click="router.back()" class="btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</template>
