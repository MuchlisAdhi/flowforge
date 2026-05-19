import { defineStore } from 'pinia'
import { ref } from 'vue'
import { workflowsApi, type WorkflowFilters } from '@/api/workflows'
import type { Workflow, WorkflowStep } from '@/types'

export const useWorkflowStore = defineStore('workflows', () => {
  const workflows = ref<Workflow[]>([])
  const currentWorkflow = ref<Workflow | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 })

  async function fetchWorkflows(filters: WorkflowFilters = {}) {
    loading.value = true
    error.value = null
    try {
      const response = await workflowsApi.list(filters)
      workflows.value = response.data
      meta.value = response.meta
    } catch (e: unknown) {
      error.value = (e as Error).message || 'Failed to fetch workflows'
    } finally {
      loading.value = false
    }
  }

  async function fetchWorkflow(id: string) {
    loading.value = true
    error.value = null
    try {
      currentWorkflow.value = await workflowsApi.get(id)
    } catch (e: unknown) {
      error.value = (e as Error).message || 'Failed to fetch workflow'
    } finally {
      loading.value = false
    }
  }

  async function createWorkflow(payload: {
    name: string
    description?: string
    timeout_seconds?: number
    steps: WorkflowStep[]
  }) {
    loading.value = true
    error.value = null
    try {
      const workflow = await workflowsApi.create(payload)
      workflows.value.unshift(workflow)
      return workflow
    } catch (e: unknown) {
      error.value = (e as Error).message || 'Failed to create workflow'
      throw e
    } finally {
      loading.value = false
    }
  }

  async function triggerWorkflow(id: string) {
    try {
      const result = await workflowsApi.trigger(id)
      return result
    } catch (e: unknown) {
      error.value = (e as Error).message || 'Failed to trigger workflow'
      throw e
    }
  }

  async function deleteWorkflow(id: string) {
    try {
      await workflowsApi.delete(id)
      workflows.value = workflows.value.filter((w) => w.id !== id)
    } catch (e: unknown) {
      error.value = (e as Error).message || 'Failed to delete workflow'
      throw e
    }
  }

  return {
    workflows,
    currentWorkflow,
    loading,
    error,
    meta,
    fetchWorkflows,
    fetchWorkflow,
    createWorkflow,
    triggerWorkflow,
    deleteWorkflow,
  }
})
