<?php

namespace App\Services;

use App\Models\WorkflowRun;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Publishes execution events to Redis for real-time SSE streaming.
 */
class EventPublisher
{
    private const CHANNEL_PREFIX = 'flowforge:execution:';

    public function publishRunStatus(WorkflowRun $run, string $status): void
    {
        $payload = [
            'type' => 'run_status',
            'run_id' => $run->id,
            'workflow_id' => $run->workflow_id,
            'tenant_id' => $run->tenant_id,
            'status' => $status,
            'timestamp' => now()->toISOString(),
        ];

        $this->publish($run->tenant_id, $payload);
    }

    public function publishStepStatus(WorkflowRun $run, string $stepId, string $status, int $attempt = 1): void
    {
        $payload = [
            'type' => 'step_status',
            'run_id' => $run->id,
            'workflow_id' => $run->workflow_id,
            'tenant_id' => $run->tenant_id,
            'step_id' => $stepId,
            'status' => $status,
            'attempt' => $attempt,
            'timestamp' => now()->toISOString(),
        ];

        $this->publish($run->tenant_id, $payload);
    }

    private function publish(string $tenantId, array $payload): void
    {
        try {
            $channel = self::CHANNEL_PREFIX . $tenantId;
            Redis::publish($channel, json_encode($payload));
        } catch (\Throwable $e) {
            // Don't fail execution if pub/sub fails
            Log::warning('Failed to publish event', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }
}
