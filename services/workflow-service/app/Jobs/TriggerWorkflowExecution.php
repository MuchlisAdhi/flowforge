<?php

namespace App\Jobs;

use App\Models\WorkflowRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Dispatches workflow run to execution service via HTTP.
 */
class TriggerWorkflowExecution implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly WorkflowRun $run
    ) {}

    public function handle(): void
    {
        // Mark as running
        $this->run->update(['status' => WorkflowRun::STATUS_RUNNING, 'started_at' => now()]);

        // Call execution service to actually execute the workflow
        $executionServiceUrl = env('EXECUTION_SERVICE_URL', 'http://execution-service:8003');

        try {
            $response = Http::timeout(10)->post("{$executionServiceUrl}/api/executions/run", [
                'run_id' => $this->run->id,
                'workflow_id' => $this->run->workflow_id,
                'workflow_version_id' => $this->run->workflow_version_id,
                'tenant_id' => $this->run->tenant_id,
            ]);

            if (!$response->successful()) {
                Log::warning("Execution service returned non-success", [
                    'run_id' => $this->run->id,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("Failed to call execution service", [
                'run_id' => $this->run->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
