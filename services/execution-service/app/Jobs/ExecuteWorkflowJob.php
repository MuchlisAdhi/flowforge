<?php

namespace App\Jobs;

use App\Models\WorkflowRun;
use App\Services\ExecutionEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max
    public int $tries = 1; // No job-level retry, retries happen at step level

    public function __construct(
        public readonly string $runId
    ) {
        $this->queue = 'workflow_execution';
    }

    public function handle(ExecutionEngine $engine): void
    {
        $run = WorkflowRun::with('version')->find($this->runId);

        if (! $run) {
            Log::error("WorkflowRun not found: {$this->runId}");
            return;
        }

        if ($run->status !== WorkflowRun::STATUS_PENDING) {
            Log::warning("WorkflowRun {$this->runId} is not in pending state: {$run->status}");
            return;
        }

        $engine->execute($run);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ExecuteWorkflowJob failed", [
            'run_id' => $this->runId,
            'error' => $exception->getMessage(),
        ]);

        $run = WorkflowRun::find($this->runId);
        $run?->markAsFailed('Job execution failed: ' . $exception->getMessage());
    }
}
