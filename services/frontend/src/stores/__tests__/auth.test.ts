import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '../auth'

// Mock localStorage
const localStorageMock = (() => {
  let store: Record<string, string> = {}
  return {
    getItem: (key: string) => store[key] || null,
    setItem: (key: string, value: string) => { store[key] = value },
    removeItem: (key: string) => { delete store[key] },
    clear: () => { store = {} },
  }
})()

Object.defineProperty(window, 'localStorage', { value: localStorageMock })

describe('Auth Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorageMock.clear()
  })

  it('starts as unauthenticated', () => {
    const store = useAuthStore()
    expect(store.isAuthenticated).toBe(false)
    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
  })

  it('logout clears state', () => {
    const store = useAuthStore()
    // Simulate logged-in state
    store.token = 'test-token'
    store.user = { id: '1', name: 'Test', email: 'test@test.com', role: 'admin', tenant_id: 't1' }

    store.logout()

    expect(store.isAuthenticated).toBe(false)
    expect(store.token).toBeNull()
    expect(store.user).toBeNull()
  })

  it('computes canWrite for admin', () => {
    const store = useAuthStore()
    store.user = { id: '1', name: 'Admin', email: 'a@t.com', role: 'admin', tenant_id: 't1' }
    expect(store.canWrite).toBe(true)
  })

  it('computes canWrite for editor', () => {
    const store = useAuthStore()
    store.user = { id: '1', name: 'Editor', email: 'e@t.com', role: 'editor', tenant_id: 't1' }
    expect(store.canWrite).toBe(true)
  })

  it('computes canWrite false for viewer', () => {
    const store = useAuthStore()
    store.user = { id: '1', name: 'Viewer', email: 'v@t.com', role: 'viewer', tenant_id: 't1' }
    expect(store.canWrite).toBe(false)
  })

  it('isAdmin computed correctly', () => {
    const store = useAuthStore()
    store.user = { id: '1', name: 'Admin', email: 'a@t.com', role: 'admin', tenant_id: 't1' }
    expect(store.isAdmin).toBe(true)
    expect(store.isEditor).toBe(false)
  })
})
