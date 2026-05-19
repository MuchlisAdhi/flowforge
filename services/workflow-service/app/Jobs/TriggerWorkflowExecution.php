<?php

namespace App\Jobs;

use App\Models\WorkflowRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatches workflow run to execution service via RabbitMQ.
 */
class TriggerWorkflowExecution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly WorkflowRun $run
    ) {
        $this->queue = 'workflow_execution';
    }

    public function handle(): void
    {
        // This job is consumed by the execution-service worker.
        // The execution service will handle the actual DAG resolution and step execution.
        // For MVP within same DB, we mark it as received here.
        // In production, this would be a message published to RabbitMQ
        // and consumed by execution-service independently.

        $this->run->update(['status' => WorkflowRun::STATUS_RUNNING, 'started_at' => now()]);
    }
}
