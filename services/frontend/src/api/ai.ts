import apiClient from './client'
import type { AIWorkflowResponse, AIFailureAnalysis } from '@/types'

export const aiApi = {
  async generateWorkflow(prompt: string): Promise<AIWorkflowResponse> {
    const { data } = await apiClient.post('/ai/workflow-builder', { prompt })
    return data
  },

  async analyzeFailure(context: {
    workflow_name: string
    step_name: string
    step_type: string
    error_message: string
    status_code?: number
    duration_ms?: number
    attempt?: number
    max_retries?: number
  }): Promise<AIFailureAnalysis> {
    const { data } = await apiClient.post('/ai/failure-analysis', context)
    return data.data
  },
}
