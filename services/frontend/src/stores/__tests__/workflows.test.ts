import { describe, it, expect, beforeEach, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useWorkflowStore } from '../workflows'

// Mock the API module
vi.mock('@/api/workflows', () => ({
  workflowsApi: {
    list: vi.fn(),
    get: vi.fn(),
    create: vi.fn(),
    update: vi.fn(),
    delete: vi.fn(),
    trigger: vi.fn(),
  },
}))

describe('Workflow Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('starts with empty state', () => {
    const store = useWorkflowStore()
    expect(store.workflows).toEqual([])
    expect(store.currentWorkflow).toBeNull()
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('sets loading state during fetch', async () => {
    const { workflowsApi } = await import('@/api/workflows')
    vi.mocked(workflowsApi.list).mockResolvedValue({
      data: [],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
    })

    const store = useWorkflowStore()
    const promise = store.fetchWorkflows()

    expect(store.loading).toBe(true)
    await promise
    expect(store.loading).toBe(false)
  })

  it('populates workflows after successful fetch', async () => {
    const { workflowsApi } = await import('@/api/workflows')
    const mockWorkflows = [
      { id: 'wf-1', name: 'Test 1', tenant_id: 't1', is_active: true, description: null, created_at: '2024-01-01', updated_at: '2024-01-01' },
      { id: 'wf-2', name: 'Test 2', tenant_id: 't1', is_active: true, description: null, created_at: '2024-01-02', updated_at: '2024-01-02' },
    ]
    vi.mocked(workflowsApi.list).mockResolvedValue({
      data: mockWorkflows,
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
    })

    const store = useWorkflowStore()
    await store.fetchWorkflows()

    expect(store.workflows).toHaveLength(2)
    expect(store.workflows[0]?.name).toBe('Test 1')
    expect(store.meta.total).toBe(2)
  })

  it('sets error on fetch failure', async () => {
    const { workflowsApi } = await import('@/api/workflows')
    vi.mocked(workflowsApi.list).mockRejectedValue(new Error('Network error'))

    const store = useWorkflowStore()
    await store.fetchWorkflows()

    expect(store.error).toBe('Network error')
    expect(store.workflows).toEqual([])
  })

  it('removes workflow from list on delete', async () => {
    const { workflowsApi } = await import('@/api/workflows')
    vi.mocked(workflowsApi.delete).mockResolvedValue(undefined)

    const store = useWorkflowStore()
    store.workflows = [
      { id: 'wf-1', name: 'Test', tenant_id: 't1', is_active: true, description: null, created_at: '2024-01-01', updated_at: '2024-01-01' },
      { id: 'wf-2', name: 'Keep', tenant_id: 't1', is_active: true, description: null, created_at: '2024-01-01', updated_at: '2024-01-01' },
    ]

    await store.deleteWorkflow('wf-1')

    expect(store.workflows).toHaveLength(1)
    expect(store.workflows[0]?.id).toBe('wf-2')
  })

  it('adds workflow to beginning of list on create', async () => {
    const { workflowsApi } = await import('@/api/workflows')
    const newWorkflow = { id: 'wf-new', name: 'New', tenant_id: 't1', is_active: true, description: null, created_at: '2024-01-03', updated_at: '2024-01-03' }
    vi.mocked(workflowsApi.create).mockResolvedValue(newWorkflow)

    const store = useWorkflowStore()
    store.workflows = [
      { id: 'wf-1', name: 'Old', tenant_id: 't1', is_active: true, description: null, created_at: '2024-01-01', updated_at: '2024-01-01' },
    ]

    await store.createWorkflow({ name: 'New', steps: [] })

    expect(store.workflows).toHaveLength(2)
    expect(store.workflows[0]?.id).toBe('wf-new')
  })

  it('triggerWorkflow calls API and returns result', async () => {
    const { workflowsApi } = await import('@/api/workflows')
    vi.mocked(workflowsApi.trigger).mockResolvedValue({ run_id: 'run-1', status: 'pending' })

    const store = useWorkflowStore()
    const result = await store.triggerWorkflow('wf-1')

    expect(result).toEqual({ run_id: 'run-1', status: 'pending' })
    expect(workflowsApi.trigger).toHaveBeenCalledWith('wf-1')
  })
})
