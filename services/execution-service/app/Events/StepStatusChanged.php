<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class StepStatusChanged implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $runId,
        public readonly string $stepId,
        public readonly string $status,
        public readonly int $attempt = 1,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("tenant.{$this->tenantId}");
    }

    public function broadcastAs(): string
    {
        return 'step.status.changed';
    }
}
