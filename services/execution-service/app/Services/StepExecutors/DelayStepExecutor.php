<?php

namespace App\Services\StepExecutors;

use RuntimeException;

class DelayStepExecutor implements StepExecutorInterface
{
    private const MAX_DELAY_SECONDS = 3600;

    public function execute(array $config, array $context = []): ?array
    {
        $durationSeconds = (int) ($config['duration_seconds'] ?? 0);

        if ($durationSeconds < 1) {
            throw new RuntimeException('Delay step: duration_seconds must be at least 1');
        }

        if ($durationSeconds > self::MAX_DELAY_SECONDS) {
            throw new RuntimeException("Delay step: duration cannot exceed {self::MAX_DELAY_SECONDS} seconds");
        }

        // Actually wait (in production, this might use a scheduled job instead)
        sleep($durationSeconds);

        return [
            'delayed_seconds' => $durationSeconds,
            'completed_at' => now()->toISOString(),
        ];
    }
}
