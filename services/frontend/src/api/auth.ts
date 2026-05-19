import apiClient from './client'
import type { LoginResponse } from '@/types'

export const authApi = {
  async login(email: string, password: string): Promise<LoginResponse> {
    const { data } = await apiClient.post('/auth/login', { email, password })
    return data
  },

  async register(payload: {
    tenant_name: string
    name: string
    email: string
    password: string
    password_confirmation: string
  }): Promise<LoginResponse> {
    const { data } = await apiClient.post('/auth/register', payload)
    return data
  },

  async me() {
    const { data } = await apiClient.get('/auth/me')
    return data.data
  },
}
