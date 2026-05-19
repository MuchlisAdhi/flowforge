<?php

use App\Services\StepExecutors\DelayStepExecutor;

beforeEach(function () {
    $this->executor = new DelayStepExecutor();
});

describe('DelayStepExecutor', function () {

    it('returns delay duration in output', function () {
        // Use 1 second delay for test (minimum allowed)
        $result = $this->executor->execute(['duration_seconds' => 1]);

        expect($result)->toHaveKey('delayed_seconds');
        expect($result['delayed_seconds'])->toBe(1);
        expect($result)->toHaveKey('completed_at');
    });

    it('throws when duration is zero', function () {
        expect(fn () => $this->executor->execute(['duration_seconds' => 0]))
            ->toThrow(RuntimeException::class, 'must be at least 1');
    });

    it('throws when duration is negative', function () {
        expect(fn () => $this->executor->execute(['duration_seconds' => -5]))
            ->toThrow(RuntimeException::class, 'must be at least 1');
    });

    it('throws when duration exceeds maximum', function () {
        expect(fn () => $this->executor->execute(['duration_seconds' => 3601]))
            ->toThrow(RuntimeException::class, 'cannot exceed');
    });

    it('throws when duration_seconds is missing', function () {
        expect(fn () => $this->executor->execute([]))
            ->toThrow(RuntimeException::class, 'must be at least 1');
    });
});
