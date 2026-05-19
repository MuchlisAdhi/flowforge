<?php

use App\Services\RetryPolicy;

beforeEach(function () {
    $this->policy = new RetryPolicy();
});

describe('Exponential Backoff', function () {

    it('calculates correct delays for exponential backoff', function () {
        // Attempt 1: 1000ms base
        $delay1 = $this->policy->calculateDelay(1, 'exponential', 1000);
        expect($delay1)->toBeGreaterThanOrEqual(900); // 1000 - 10% jitter
        expect($delay1)->toBeLessThanOrEqual(1100); // 1000 + 10% jitter

        // Attempt 2: 2000ms
        $delay2 = $this->policy->calculateDelay(2, 'exponential', 1000);
        expect($delay2)->toBeGreaterThanOrEqual(1800);
        expect($delay2)->toBeLessThanOrEqual(2200);

        // Attempt 3: 4000ms
        $delay3 = $this->policy->calculateDelay(3, 'exponential', 1000);
        expect($delay3)->toBeGreaterThanOrEqual(3600);
        expect($delay3)->toBeLessThanOrEqual(4400);
    });

    it('caps delay at 60 seconds', function () {
        // Attempt 7 with 1000ms base = 64000ms, should be capped at 60000
        $delay = $this->policy->calculateDelay(7, 'exponential', 1000);
        expect($delay)->toBeLessThanOrEqual(60000);
    });

    it('handles custom initial delay', function () {
        $delay = $this->policy->calculateDelay(1, 'exponential', 500);
        expect($delay)->toBeGreaterThanOrEqual(450);
        expect($delay)->toBeLessThanOrEqual(550);
    });
});

describe('Linear Backoff', function () {

    it('calculates correct delays for linear backoff', function () {
        // Attempt 1: 1000ms
        $delay1 = $this->policy->calculateDelay(1, 'linear', 1000);
        expect($delay1)->toBeGreaterThanOrEqual(900);
        expect($delay1)->toBeLessThanOrEqual(1100);

        // Attempt 2: 2000ms
        $delay2 = $this->policy->calculateDelay(2, 'linear', 1000);
        expect($delay2)->toBeGreaterThanOrEqual(1800);
        expect($delay2)->toBeLessThanOrEqual(2200);

        // Attempt 3: 3000ms
        $delay3 = $this->policy->calculateDelay(3, 'linear', 1000);
        expect($delay3)->toBeGreaterThanOrEqual(2700);
        expect($delay3)->toBeLessThanOrEqual(3300);
    });

    it('increases linearly', function () {
        $delay1 = $this->policy->calculateDelay(1, 'linear', 1000);
        $delay5 = $this->policy->calculateDelay(5, 'linear', 1000);

        // delay5 should be approximately 5x delay1 (within jitter tolerance)
        expect($delay5)->toBeGreaterThan($delay1 * 3);
    });
});

describe('Edge Cases', function () {

    it('handles unknown backoff type as initial delay', function () {
        $delay = $this->policy->calculateDelay(1, 'unknown', 1000);
        expect($delay)->toBeGreaterThanOrEqual(900);
        expect($delay)->toBeLessThanOrEqual(1100);
    });

    it('always returns positive value', function () {
        for ($i = 1; $i <= 10; $i++) {
            $delay = $this->policy->calculateDelay($i, 'exponential', 100);
            expect($delay)->toBeGreaterThan(0);
        }
    });
});
