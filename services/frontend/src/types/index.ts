export interface User {
  id: string
  name: string
  email: string
  role: 'admin' | 'editor' | 'viewer'
  tenant_id: string
}

export interface LoginResponse {
  message: string
  data: {
    user: User
    token: string
    token_type: string
    expires_in: number
  }
}

export interface Workflow {
  id: string
  tenant_id: string
  name: string
  description: string | null
  is_active: boolean
  created_at: string
  updated_at: string
  latest_version?: WorkflowVersion
}

export interface WorkflowVersion {
  id: string
  workflow_id: string
  version: number
  definition: WorkflowDefinition
  timeout_seconds: number
  change_note: string | null
  created_at: string
}

export interface WorkflowDefinition {
  steps: WorkflowStep[]
  execution_plan?: string[][]
}

export interface WorkflowStep {
  id: string
  type: 'http' | 'script' | 'delay' | 'condition'
  name: string
  depends_on: string[]
  config: Record<string, unknown>
  retry?: RetryConfig
}

export interface RetryConfig {
  max_retries: number
  backoff: 'exponential' | 'linear'
  initial_delay_ms: number
}

export interface WorkflowRun {
  id: string
  tenant_id: string
  workflow_id: string
  workflow_version_id: string
  status: RunStatus
  trigger_type: 'manual' | 'scheduled' | 'webhook'
  triggered_by: string | null
  started_at: string | null
  completed_at: string | null
  error_message: string | null
  created_at: string
  workflow?: { id: string; name: string }
  version?: WorkflowVersion
  step_runs?: StepRun[]
}

export type RunStatus = 'pending' | 'running' | 'success' | 'failed' | 'cancelled' | 'timeout'

export interface StepRun {
  id: string
  run_id: string
  step_id: string
  step_name: string
  step_type: string
  status: 'pending' | 'running' | 'success' | 'failed' | 'skipped'
  attempt: number
  output: Record<string, unknown> | null
  error_message: string | null
  started_at: string | null
  completed_at: string | null
}

export interface HealthMetrics {
  active_runs: number
  last_24h: {
    total_runs: number
    success_count: number
    failed_count: number
    timeout_count: number
    success_rate: number
    failure_rate: number
    avg_duration_seconds: number
  }
  calculated_at: string
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export interface AIWorkflowResponse {
  message: string
  data: {
    name: string
    description: string
    timeout_seconds: number
    steps: WorkflowStep[]
  }
  requires_review: boolean
}

export interface AIFailureAnalysis {
  diagnosis: string
  root_cause: string
  suggestions: string[]
  confidence: number
}

// SSE Event types
export interface SSEEvent {
  type: 'run_status' | 'step_status'
  run_id: string
  workflow_id: string
  tenant_id: string
  status: string
  step_id?: string
  attempt?: number
  timestamp: string
}
