<?php

use App\Services\Providers\MockAIProvider;

beforeEach(function () {
    $this->provider = new MockAIProvider();
});

describe('Workflow Generation', function () {

    it('generates a valid workflow from prompt', function () {
        $result = $this->provider->generateWorkflowFromPrompt(
            'Setiap jam 8 pagi, ambil data mahasiswa dari API, validasi jumlah data, lalu kirim ringkasan ke webhook',
            ['tenant_id' => 'test-tenant']
        );

        expect($result)->toHaveKeys(['name', 'description', 'timeout_seconds', 'steps']);
        expect($result['steps'])->toBeArray();
        expect(count($result['steps']))->toBeGreaterThanOrEqual(2);

        // Verify step structure
        foreach ($result['steps'] as $step) {
            expect($step)->toHaveKeys(['id', 'type', 'name', 'depends_on', 'config']);
            expect($step['type'])->toBeIn(['http', 'delay', 'condition', 'script']);
        }
    });

    it('includes validation step for validation keywords', function () {
        $result = $this->provider->generateWorkflowFromPrompt('check data and validate results');

        $types = array_column($result['steps'], 'type');
        expect($types)->toContain('condition');
    });

    it('includes send step for notification keywords', function () {
        $result = $this->provider->generateWorkflowFromPrompt('kirim data ke webhook');

        $hasPost = false;
        foreach ($result['steps'] as $step) {
            if ($step['type'] === 'http' && ($step['config']['method'] ?? '') === 'POST') {
                $hasPost = true;
            }
        }
        expect($hasPost)->toBeTrue();
    });

    it('respects depends_on ordering', function () {
        $result = $this->provider->generateWorkflowFromPrompt('fetch, validate, then send');

        // First step should have no dependencies
        expect($result['steps'][0]['depends_on'])->toBe([]);

        // Subsequent steps should depend on previous
        for ($i = 1; $i < count($result['steps']); $i++) {
            expect($result['steps'][$i]['depends_on'])->not->toBe([]);
        }
    });
});

describe('Failure Analysis', function () {

    it('returns diagnosis for timeout error', function () {
        $result = $this->provider->analyzeFailure([
            'error_message' => 'Connection timeout after 30s',
            'step_type' => 'http',
        ]);

        expect($result)->toHaveKeys(['diagnosis', 'root_cause', 'suggestions', 'confidence']);
        expect($result['root_cause'])->toBe('timeout');
        expect($result['suggestions'])->toBeArray();
        expect(count($result['suggestions']))->toBeGreaterThanOrEqual(1);
    });

    it('returns diagnosis for auth error', function () {
        $result = $this->provider->analyzeFailure([
            'error_message' => 'HTTP 401 Unauthorized',
            'step_type' => 'http',
        ]);

        expect($result['root_cause'])->toBe('auth');
    });

    it('returns unknown for unclassifiable error', function () {
        $result = $this->provider->analyzeFailure([
            'error_message' => 'Something weird happened',
            'step_type' => 'script',
        ]);

        expect($result['root_cause'])->toBe('unknown');
    });
});
