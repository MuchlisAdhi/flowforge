<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * DTO representing the result of triggering a workflow.
 */
final readonly class TriggerResult
{
    public function __construct(
        public string $runId,
        public string $status,
        public string $workflowId,
        public int $version,
    ) {}
}
