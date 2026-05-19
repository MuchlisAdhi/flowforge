<?php

namespace App\Services;

use App\Events\StepStatusChanged;
use App\Events\RunStatusChanged;
use App\Models\WorkflowRun;
use App\Models\WorkflowStepRun;
use App\Models\WorkflowExecutionLog;
use App\Services\StepExecutors\StepExecutorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExecutionEngine
{
    private int $globalTimeout;
    private float $startTime;

    public function __construct(
        private readonly StepExecutorFactory $executorFactory,
        private readonly RetryPolicy $retryPolicy,
        private readonly EventPublisher $eventPublisher,
    ) {
        $this->globalTimeout = (int) config('execution.global_timeout', 3600);
    }

    /**
     * Execute a workflow run.
     */
    public function execute(WorkflowRun $run): void
    {
        $this->startTime = microtime(true);

        $run->markAsRunning();
        $this->eventPublisher->publishRunStatus($run, 'running');
        $this->log($run, 'info', 'Workflow execution started');

        $version = $run->version;
        $definition = $version->definition;
        $steps = $definition['steps'] ?? [];
        $levels = $definition['execution_plan'] ?? [];

        if (empty($steps) || empty($levels)) {
            $run->markAsFailed('No steps or execution plan found');
            $this->eventPublisher->publishRunStatus($run, 'failed');
            return;
        }

        // Create step run records
        $stepRunMap = $this->createStepRuns($run, $steps);

        // Execute level by level (parallel within each level)
        try {
            foreach ($levels as $level => $stepIds) {
                if ($this->isTimedOut()) {
                    $run->markAsTimeout();
                    $this->eventPublisher->publishRunStatus($run, 'timeout');
                    $this->log($run, 'error', 'Workflow timed out');
                    return;
                }

                $this->log($run, 'info', "Executing level {$level}: " . implode(', ', $stepIds));

                // Execute all steps in this level (parallel-ready)
                $levelSuccess = $this->executeLevelSteps($run, $steps, $stepIds, $stepRunMap);

                if (! $levelSuccess) {
                    $run->markAsFailed('Step execution failed');
                    $this->eventPublisher->publishRunStatus($run, 'failed');
                    $this->log($run, 'error', 'Workflow failed at level ' . $level);
                    return;
                }
            }

            $run->markAsSuccess();
            $this->eventPublisher->publishRunStatus($run, 'success');
            $this->log($run, 'info', 'Workflow completed successfully');

        } catch (\Throwable $e) {
            $run->markAsFailed($e->getMessage());
            $this->eventPublisher->publishRunStatus($run, 'failed');
            $this->log($run, 'error', 'Workflow execution error: ' . $e->getMessage());
            Log::error('Workflow execution error', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Create step run records for all steps in the workflow.
     */
    private function createStepRuns(WorkflowRun $run, array $steps): array
    {
        $map = [];

        foreach ($steps as $step) {
            $stepRun = WorkflowStepRun::create([
                'run_id' => $run->id,
                'step_id' => $step['id'],
                'step_name' => $step['name'],
                'step_type' => $step['type'],
                'status' => WorkflowStepRun::STATUS_PENDING,
                'attempt' => 0,
            ]);
            $map[$step['id']] = $stepRun;
        }

        return $map;
    }

    /**
     * Execute all steps in a given level. Returns false if any critical step fails.
     */
    private function executeLevelSteps(WorkflowRun $run, array $allSteps, array $stepIds, array &$stepRunMap): bool
    {
        $stepsById = collect($allSteps)->keyBy('id');
        $results = [];

        // For MVP, execute sequentially within level
        // In production, use async/fiber/parallel processing
        foreach ($stepIds as $stepId) {
            if ($this->isTimedOut()) {
                return false;
            }

            $stepDef = $stepsById->get($stepId);
            if (! $stepDef) {
                continue;
            }

            $stepRun = $stepRunMap[$stepId];
            $results[$stepId] = $this->executeStepWithRetry($run, $stepDef, $stepRun);
        }

        // Check if all steps succeeded
        foreach ($results as $success) {
            if (! $success) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute a single step with retry logic.
     */
    private function executeStepWithRetry(WorkflowRun $run, array $stepDef, WorkflowStepRun $stepRun): bool
    {
        $retryConfig = $stepDef['retry'] ?? [];
        $maxRetries = $retryConfig['max_retries'] ?? 0;
        $backoff = $retryConfig['backoff'] ?? 'exponential';
        $initialDelay = $retryConfig['initial_delay_ms'] ?? 1000;

        $attempt = 0;

        while ($attempt <= $maxRetries) {
            $attempt++;

            $stepRun->update([
                'status' => WorkflowStepRun::STATUS_RUNNING,
                'attempt' => $attempt,
                'started_at' => now(),
            ]);

            $this->eventPublisher->publishStepStatus($run, $stepDef['id'], 'running', $attempt);
            $this->log($run, 'info', "Step '{$stepDef['name']}' started (attempt {$attempt})", $stepDef['id']);

            try {
                $executor = $this->executorFactory->make($stepDef['type']);
                $output = $executor->execute($stepDef['config'], $this->getStepContext($run, $stepDef));

                $stepRun->update([
                    'status' => WorkflowStepRun::STATUS_SUCCESS,
                    'output' => $output,
                    'completed_at' => now(),
                ]);

                $this->eventPublisher->publishStepStatus($run, $stepDef['id'], 'success', $attempt);
                $this->log($run, 'info', "Step '{$stepDef['name']}' completed successfully", $stepDef['id']);

                return true;

            } catch (\Throwable $e) {
                $this->log($run, 'warning', "Step '{$stepDef['name']}' failed (attempt {$attempt}): {$e->getMessage()}", $stepDef['id']);

                if ($attempt <= $maxRetries) {
                    $delay = $this->retryPolicy->calculateDelay($attempt, $backoff, $initialDelay);
                    $this->log($run, 'info', "Retrying step '{$stepDef['name']}' in {$delay}ms", $stepDef['id']);
                    usleep($delay * 1000); // Convert ms to microseconds
                } else {
                    $stepRun->update([
                        'status' => WorkflowStepRun::STATUS_FAILED,
                        'error_message' => substr($e->getMessage(), 0, 2000),
                        'completed_at' => now(),
                    ]);

                    $this->eventPublisher->publishStepStatus($run, $stepDef['id'], 'failed', $attempt);
                    $this->log($run, 'error', "Step '{$stepDef['name']}' permanently failed after {$attempt} attempts", $stepDef['id']);

                    return false;
                }
            }
        }

        return false;
    }

    private function getStepContext(WorkflowRun $run, array $stepDef): array
    {
        // Get outputs from completed dependency steps
        $previousOutputs = [];

        foreach ($stepDef['depends_on'] as $depId) {
            $depRun = WorkflowStepRun::where('run_id', $run->id)
                ->where('step_id', $depId)
                ->first();

            if ($depRun) {
                $previousOutputs[$depId] = $depRun->output;
            }
        }

        return [
            'run_id' => $run->id,
            'step_id' => $stepDef['id'],
            'previous_outputs' => $previousOutputs,
        ];
    }

    private function isTimedOut(): bool
    {
        return (microtime(true) - $this->startTime) >= $this->globalTimeout;
    }

    private function log(WorkflowRun $run, string $level, string $message, ?string $stepId = null): void
    {
        WorkflowExecutionLog::create([
            'run_id' => $run->id,
            'step_id' => $stepId,
            'level' => $level,
            'message' => $message,
            'context' => [
                'elapsed_seconds' => round(microtime(true) - $this->startTime, 2),
            ],
            'created_at' => now(),
        ]);
    }
}
