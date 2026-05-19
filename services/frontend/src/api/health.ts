import apiClient from './client'
import type { HealthMetrics } from '@/types'

export const healthApi = {
  async getMetrics(): Promise<HealthMetrics> {
    const { data } = await apiClient.get('/health/metrics')
    return data.data
  },
}
