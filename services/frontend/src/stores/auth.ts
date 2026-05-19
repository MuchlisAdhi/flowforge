import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/api/auth'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('ff_token'))
  const user = ref<User | null>(JSON.parse(localStorage.getItem('ff_user') || 'null'))

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isEditor = computed(() => user.value?.role === 'editor')
  const canWrite = computed(() => ['admin', 'editor'].includes(user.value?.role || ''))

  async function login(email: string, password: string) {
    const response = await authApi.login(email, password)
    token.value = response.data.token
    user.value = response.data.user
    localStorage.setItem('ff_token', response.data.token)
    localStorage.setItem('ff_user', JSON.stringify(response.data.user))
  }

  async function register(payload: {
    tenant_name: string
    name: string
    email: string
    password: string
    password_confirmation: string
  }) {
    const response = await authApi.register(payload)
    token.value = response.data.token
    user.value = response.data.user
    localStorage.setItem('ff_token', response.data.token)
    localStorage.setItem('ff_user', JSON.stringify(response.data.user))
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('ff_token')
    localStorage.removeItem('ff_user')
  }

  return {
    token,
    user,
    isAuthenticated,
    isAdmin,
    isEditor,
    canWrite,
    login,
    register,
    logout,
  }
})
