import apiClient from './client'
import type { WorkflowRun, PaginatedResponse } from '@/types'

export interface RunFilters {
  status?: string
  workflow_id?: string
  from?: string
  to?: string
  sort_by?: string
  sort_dir?: 'asc' | 'desc'
  page?: number
  per_page?: number
}

export const runsApi = {
  async list(filters: RunFilters = {}): Promise<PaginatedResponse<WorkflowRun>> {
    const { data } = await apiClient.get('/runs', { params: filters })
    return data
  },

  async get(id: string): Promise<WorkflowRun> {
    const { data } = await apiClient.get(`/runs/${id}`)
    return data.data
  },
}
