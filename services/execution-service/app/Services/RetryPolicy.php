<?php

namespace App\Services;

class RetryPolicy
{
    /**
     * Calculate delay in milliseconds for a given retry attempt.
     *
     * @param int $attempt Current attempt number (1-based)
     * @param string $backoff Backoff strategy: 'exponential' or 'linear'
     * @param int $initialDelayMs Initial delay in milliseconds
     * @return int Delay in milliseconds
     */
    public function calculateDelay(int $attempt, string $backoff = 'exponential', int $initialDelayMs = 1000): int
    {
        $delay = match ($backoff) {
            'exponential' => $this->exponentialBackoff($attempt, $initialDelayMs),
            'linear' => $this->linearBackoff($attempt, $initialDelayMs),
            default => $initialDelayMs,
        };

        // Add jitter (±10%) to prevent thundering herd
        $jitter = (int) ($delay * 0.1);
        $delay += random_int(-$jitter, $jitter);

        // Cap at 60 seconds
        return min($delay, 60000);
    }

    /**
     * Exponential backoff: delay = initialDelay * 2^(attempt-1)
     * Attempt 1: 1000ms, Attempt 2: 2000ms, Attempt 3: 4000ms, ...
     */
    private function exponentialBackoff(int $attempt, int $initialDelayMs): int
    {
        return $initialDelayMs * (int) pow(2, $attempt - 1);
    }

    /**
     * Linear backoff: delay = initialDelay * attempt
     * Attempt 1: 1000ms, Attempt 2: 2000ms, Attempt 3: 3000ms, ...
     */
    private function linearBackoff(int $attempt, int $initialDelayMs): int
    {
        return $initialDelayMs * $attempt;
    }
}
