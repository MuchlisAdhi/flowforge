import apiClient from './client'
import type { Workflow, WorkflowStep, PaginatedResponse } from '@/types'

export interface WorkflowFilters {
  search?: string
  status?: 'active' | 'inactive'
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export const workflowsApi = {
  async list(filters: WorkflowFilters = {}): Promise<PaginatedResponse<Workflow>> {
    const { data } = await apiClient.get('/workflows', { params: filters })
    return data
  },

  async get(id: string): Promise<Workflow> {
    const { data } = await apiClient.get(`/workflows/${id}`)
    return data.data
  },

  async create(payload: {
    name: string
    description?: string
    timeout_seconds?: number
    steps: WorkflowStep[]
  }): Promise<Workflow> {
    const { data } = await apiClient.post('/workflows', payload)
    return data.data
  },

  async update(
    id: string,
    payload: {
      name?: string
      description?: string
      timeout_seconds?: number
      steps?: WorkflowStep[]
      change_note?: string
    }
  ): Promise<Workflow> {
    const { data } = await apiClient.put(`/workflows/${id}`, payload)
    return data.data
  },

  async delete(id: string): Promise<void> {
    await apiClient.delete(`/workflows/${id}`)
  },

  async rollback(id: string, version: number): Promise<Workflow> {
    const { data } = await apiClient.post(`/workflows/${id}/versions/${version}/rollback`)
    return data.data
  },

  async trigger(id: string): Promise<{ run_id: string; status: string }> {
    const { data } = await apiClient.post(`/workflows/${id}/trigger`)
    return data.data
  },
}
