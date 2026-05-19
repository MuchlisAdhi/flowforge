<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class RunStatusChanged implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $runId,
        public readonly string $workflowId,
        public readonly string $status,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("tenant.{$this->tenantId}");
    }

    public function broadcastAs(): string
    {
        return 'run.status.changed';
    }
}
