import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useRunStore } from '../runs'

describe('Run Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('starts with empty state', () => {
    const store = useRunStore()
    expect(store.runs).toEqual([])
    expect(store.currentRun).toBeNull()
    expect(store.loading).toBe(false)
    expect(store.error).toBeNull()
  })

  it('updateRunStatus updates run in list', () => {
    const store = useRunStore()
    store.runs = [
      { id: 'run-1', status: 'pending', tenant_id: 't1', workflow_id: 'wf-1', workflow_version_id: 'v1', trigger_type: 'manual', triggered_by: null, started_at: null, completed_at: null, error_message: null, created_at: '2024-01-01' },
    ] as any

    store.updateRunStatus('run-1', 'running')

    expect(store.runs[0]?.status).toBe('running')
  })

  it('updateRunStatus updates currentRun when matching', () => {
    const store = useRunStore()
    store.currentRun = {
      id: 'run-1', status: 'running', tenant_id: 't1', workflow_id: 'wf-1', workflow_version_id: 'v1',
      trigger_type: 'manual', triggered_by: null, started_at: null, completed_at: null, error_message: null, created_at: '2024-01-01',
    } as any

    store.updateRunStatus('run-1', 'success')

    expect(store.currentRun?.status).toBe('success')
  })

  it('updateRunStatus does not modify non-matching runs', () => {
    const store = useRunStore()
    store.runs = [
      { id: 'run-1', status: 'pending' },
      { id: 'run-2', status: 'running' },
    ] as any

    store.updateRunStatus('run-2', 'success')

    expect(store.runs[0]?.status).toBe('pending')
    expect(store.runs[1]?.status).toBe('success')
  })

  it('updateStepStatus updates specific step in currentRun', () => {
    const store = useRunStore()
    store.currentRun = {
      id: 'run-1', status: 'running',
      step_runs: [
        { id: 's1', step_id: 'fetch_data', status: 'running' },
        { id: 's2', step_id: 'validate', status: 'pending' },
      ],
    } as any

    store.updateStepStatus('run-1', 'fetch_data', 'success')

    expect(store.currentRun?.step_runs?.[0]?.status).toBe('success')
    expect(store.currentRun?.step_runs?.[1]?.status).toBe('pending')
  })

  it('updateStepStatus ignores when run ID does not match', () => {
    const store = useRunStore()
    store.currentRun = {
      id: 'run-1', status: 'running',
      step_runs: [{ id: 's1', step_id: 'fetch_data', status: 'running' }],
    } as any

    store.updateStepStatus('run-other', 'fetch_data', 'success')

    // Should not change
    expect(store.currentRun?.step_runs?.[0]?.status).toBe('running')
  })
})
