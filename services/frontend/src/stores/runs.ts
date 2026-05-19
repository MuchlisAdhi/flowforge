import { defineStore } from 'pinia'
import { ref } from 'vue'
import { runsApi, type RunFilters } from '@/api/runs'
import type { WorkflowRun } from '@/types'

export const useRunStore = defineStore('runs', () => {
  const runs = ref<WorkflowRun[]>([])
  const currentRun = ref<WorkflowRun | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const meta = ref({ current_page: 1, last_page: 1, per_page: 20, total: 0 })

  async function fetchRuns(filters: RunFilters = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await runsApi.list(filters)
      runs.value = response.data
      meta.value = response.meta
    } catch (e: unknown) {
      error.value = (e as Error).message || 'Failed to fetch runs'
    } finally {
      loading.value = false
    }
  }

  async function fetchRun(id: string) {
    loading.value = true
    error.value = null
    try {
      currentRun.value = await runsApi.get(id)
    } catch (e: unknown) {
      error.value = (e as Error).message || 'Failed to fetch run'
    } finally {
      loading.value = false
    }
  }

  // Update run/step status from SSE events
  function updateRunStatus(runId: string, status: string) {
    const run = runs.value.find((r) => r.id === runId)
    if (run) {
      run.status = status as WorkflowRun['status']
    }
    if (currentRun.value?.id === runId) {
      currentRun.value.status = status as WorkflowRun['status']
    }
  }

  function updateStepStatus(runId: string, stepId: string, status: string) {
    if (currentRun.value?.id === runId && currentRun.value.step_runs) {
      const step = currentRun.value.step_runs.find((s) => s.step_id === stepId)
      if (step) {
        step.status = status as 'pending' | 'running' | 'success' | 'failed' | 'skipped'
      }
    }
  }

  return {
    runs,
    currentRun,
    loading,
    error,
    meta,
    fetchRuns,
    fetchRun,
    updateRunStatus,
    updateStepStatus,
  }
})
