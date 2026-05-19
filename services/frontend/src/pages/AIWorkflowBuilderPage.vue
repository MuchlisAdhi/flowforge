<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { aiApi } from '@/api/ai'
import { useWorkflowStore } from '@/stores/workflows'
import DagVisualization from '@/components/DagVisualization.vue'
import type { WorkflowStep } from '@/types'

const router = useRouter()
const workflowStore = useWorkflowStore()

const prompt = ref('')
const loading = ref(false)
const error = ref('')
const generatedName = ref('')
const generatedDescription = ref('')
const generatedSteps = ref<WorkflowStep[]>([])
const hasResult = ref(false)
const saving = ref(false)

const examples = [
  'Setiap jam 8 pagi, ambil data mahasiswa dari API A, validasi jumlah data, lalu kirim ringkasan ke webhook B.',
  'Fetch user list from CRM, wait 5 seconds for processing, then send notification via webhook.',
  'Download daily report from analytics API, check if status is success, then export to CSV.',
]

async function handleGenerate() {
  if (!prompt.value.trim()) return
  loading.value = true
  error.value = ''
  hasResult.value = false

  try {
    const response = await aiApi.generateWorkflow(prompt.value)
    generatedName.value = response.data.name
    generatedDescription.value = response.data.description
    generatedSteps.value = response.data.steps
    hasResult.value = true
  } catch (e: unknown) {
    const err = e as { response?: { data?: { error?: string; suggestion?: string } } }
    error.value = err.response?.data?.error || err.response?.data?.suggestion || 'Failed to generate workflow'
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  saving.value = true
  try {
    const workflow = await workflowStore.createWorkflow({
      name: generatedName.value,
      description: generatedDescription.value,
      timeout_seconds: 300,
      steps: generatedSteps.value,
    })
    router.push(`/workflows/${workflow.id}`)
  } catch (e: unknown) {
    error.value = 'Failed to save workflow. The DAG may have validation issues.'
  } finally {
    saving.value = false
  }
}

function useExample(example: string) {
  prompt.value = example
}
</script>

<template>
  <div>
    <h1 class="text-2xl font-bold mb-2">🤖 AI Workflow Builder</h1>
    <p class="text-gray-500 mb-6">Describe your workflow in natural language and AI will generate the DAG definition.</p>

    <!-- Input -->
    <div class="card mb-6">
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Describe your workflow
      </label>
      <textarea
        v-model="prompt"
        class="input font-normal"
        rows="4"
        placeholder="Example: Setiap jam 8 pagi, ambil data mahasiswa dari API, validasi jumlah data, lalu kirim ringkasan ke webhook..."
      ></textarea>

      <div class="mt-3 flex flex-wrap gap-2">
        <button
          v-for="example in examples"
          :key="example"
          @click="useExample(example)"
          class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded transition-colors"
        >
          {{ example.substring(0, 50) }}...
        </button>
      </div>

      <button
        @click="handleGenerate"
        class="btn-primary mt-4"
        :disabled="loading || !prompt.trim()"
      >
        {{ loading ? '🔄 Generating...' : '✨ Generate Workflow' }}
      </button>
    </div>

    <!-- Error -->
    <div v-if="error" class="p-4 bg-red-50 text-red-700 rounded-lg mb-6">
      {{ error }}
    </div>

    <!-- Result -->
    <template v-if="hasResult">
      <div class="card mb-6">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h2 class="font-semibold text-lg">{{ generatedName }}</h2>
            <p class="text-gray-500 text-sm">{{ generatedDescription }}</p>
          </div>
          <div class="flex gap-2">
            <button @click="handleSave" :disabled="saving" class="btn-success">
              {{ saving ? 'Saving...' : '💾 Save Workflow' }}
            </button>
          </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4 text-sm text-yellow-800">
          ⚠️ Please review the generated workflow before saving. AI output may need adjustments.
        </div>

        <!-- DAG Preview -->
        <h3 class="font-medium mb-2">DAG Preview</h3>
        <DagVisualization :steps="generatedSteps" />
      </div>

      <!-- Raw JSON -->
      <div class="card">
        <h3 class="font-medium mb-2">Generated JSON</h3>
        <pre class="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-xs">{{ JSON.stringify(generatedSteps, null, 2) }}</pre>
      </div>
    </template>
  </div>
</template>
