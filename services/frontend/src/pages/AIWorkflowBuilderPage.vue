<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { aiApi } from '@/api/ai'
import { useWorkflowStore } from '@/stores/workflows'
import DagVisualization from '@/components/DagVisualization.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import ErrorAlert from '@/components/ui/ErrorAlert.vue'
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
  {
    label: 'Student sync + notify',
    prompt: 'Setiap jam 8 pagi, ambil data mahasiswa dari API A, validasi jumlah data, lalu kirim ringkasan ke webhook B.',
  },
  {
    label: 'Report generation',
    prompt: 'Download daily report from analytics API, wait 5 seconds for processing, check if status is success, then export to CSV.',
  },
  {
    label: 'Data pipeline',
    prompt: 'Fetch user list from CRM, validate response is 200, send notification via webhook when done.',
  },
]

async function handleGenerate() {
  if (!prompt.value.trim() || loading.value) return
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
    error.value = err.response?.data?.suggestion || err.response?.data?.error || 'Failed to generate workflow. Try rephrasing your description.'
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  saving.value = true
  error.value = ''
  try {
    const workflow = await workflowStore.createWorkflow({
      name: generatedName.value,
      description: generatedDescription.value,
      timeout_seconds: 300,
      steps: generatedSteps.value,
    })
    router.push(`/workflows/${workflow.id}`)
  } catch {
    error.value = 'Failed to save workflow. The DAG may have validation issues.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader
      title="AI Workflow Builder"
      subtitle="Describe your workflow in natural language — AI generates the DAG"
    />

    <!-- Input section -->
    <div class="card mb-6">
      <div class="flex items-center gap-2 mb-3">
        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
          <span class="text-white text-sm">🤖</span>
        </div>
        <div>
          <h2 class="text-sm font-semibold text-gray-900">Describe your workflow</h2>
          <p class="text-xs text-gray-500">Be specific about triggers, steps, and conditions</p>
        </div>
      </div>

      <textarea
        v-model="prompt"
        class="input resize-none"
        rows="4"
        placeholder="Example: Setiap jam 8 pagi, ambil data mahasiswa dari API, validasi jumlah data, lalu kirim ringkasan ke webhook..."
        @keydown.ctrl.enter="handleGenerate"
      ></textarea>

      <!-- Example chips -->
      <div class="flex flex-wrap gap-2 mt-3">
        <button
          v-for="ex in examples"
          :key="ex.label"
          @click="prompt = ex.prompt"
          class="text-xs bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-3 py-1.5 rounded-full transition-colors font-medium"
        >
          {{ ex.label }}
        </button>
      </div>

      <div class="flex items-center justify-between mt-4">
        <p class="text-xs text-gray-400">Ctrl+Enter to generate</p>
        <button
          @click="handleGenerate"
          class="btn-primary"
          :disabled="loading || !prompt.trim()"
        >
          <svg v-if="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ loading ? 'Generating...' : '✨ Generate Workflow' }}
        </button>
      </div>
    </div>

    <!-- Error -->
    <ErrorAlert v-if="error" :message="error" dismissible @dismiss="error = ''" />

    <!-- Generated result -->
    <Transition name="slide">
      <div v-if="hasResult" class="space-y-6">
        <!-- Review banner -->
        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-center gap-3">
          <svg class="w-5 h-5 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
          </svg>
          <div class="flex-1">
            <p class="text-sm font-medium text-amber-800">Review before saving</p>
            <p class="text-xs text-amber-700">AI-generated workflow. Verify steps and configuration are correct.</p>
          </div>
          <button @click="handleSave" :disabled="saving" class="btn-success">
            {{ saving ? 'Saving...' : '💾 Save Workflow' }}
          </button>
        </div>

        <!-- Generated workflow card -->
        <div class="card">
          <div class="mb-4">
            <h3 class="text-lg font-semibold text-gray-900">{{ generatedName }}</h3>
            <p class="text-sm text-gray-500 mt-1">{{ generatedDescription }}</p>
          </div>

          <!-- DAG Preview -->
          <DagVisualization :steps="generatedSteps" height="300px" />
        </div>

        <!-- Steps detail -->
        <div class="card">
          <h3 class="text-sm font-semibold text-gray-900 mb-3">Generated Steps ({{ generatedSteps.length }})</h3>
          <div class="divide-y divide-gray-100">
            <div v-for="(step, i) in generatedSteps" :key="step.id" class="py-3 flex items-start gap-3">
              <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0 text-xs font-bold text-indigo-700">
                {{ i + 1 }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-gray-900">{{ step.name }}</span>
                  <span class="badge-info text-[10px]">{{ step.type }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5 font-mono">id: {{ step.id }}</p>
                <p v-if="step.depends_on.length" class="text-xs text-gray-400 mt-0.5">
                  depends on: {{ step.depends_on.join(', ') }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Raw JSON (collapsed) -->
        <details class="card">
          <summary class="text-sm font-semibold text-gray-900 cursor-pointer">
            Raw JSON Definition
          </summary>
          <pre class="mt-3 bg-gray-900 text-emerald-400 p-4 rounded-lg overflow-x-auto text-xs leading-relaxed">{{ JSON.stringify(generatedSteps, null, 2) }}</pre>
        </details>
      </div>
    </Transition>
  </div>
</template>
