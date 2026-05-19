<?php

use App\Exceptions\DagCycleException;
use App\Exceptions\DagValidationException;
use App\Services\DagParser;

beforeEach(function () {
    $this->parser = new DagParser();
});

describe('DAG Parser - Complex Graph Structures', function () {

    it('handles a wide parallel DAG (10 parallel root nodes)', function () {
        $steps = [];
        for ($i = 1; $i <= 10; $i++) {
            $steps[] = [
                'id' => "parallel_{$i}",
                'type' => 'http',
                'name' => "Parallel Step {$i}",
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => "https://api.example.com/{$i}", 'headers' => []],
            ];
        }
        // Final merge step depends on all 10
        $steps[] = [
            'id' => 'merge_all',
            'type' => 'condition',
            'name' => 'Merge All',
            'depends_on' => array_map(fn ($i) => "parallel_{$i}", range(1, 10)),
            'config' => ['expression' => 'true'],
        ];

        $result = $this->parser->validate($steps);

        expect($result['total_levels'])->toBe(2);
        expect(count($result['levels'][0]))->toBe(10);
        expect($result['levels'][1])->toBe(['merge_all']);
    });

    it('handles a deep linear chain (20 sequential steps)', function () {
        $steps = [];
        for ($i = 1; $i <= 20; $i++) {
            $steps[] = [
                'id' => "step_{$i}",
                'type' => 'delay',
                'name' => "Step {$i}",
                'depends_on' => $i > 1 ? ["step_" . ($i - 1)] : [],
                'config' => ['duration_seconds' => 1],
            ];
        }

        $result = $this->parser->validate($steps);

        expect($result['total_levels'])->toBe(20);
        expect(count($result['sorted']))->toBe(20);
    });

    it('handles complex multi-branch DAG', function () {
        //       A
        //      / \
        //     B   C
        //    / \   \
        //   D   E   F
        //    \ / \ /
        //     G   H
        //      \ /
        //       I
        $steps = [
            ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []]],
            ['id' => 'b', 'type' => 'delay', 'name' => 'B', 'depends_on' => ['a'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'c', 'type' => 'delay', 'name' => 'C', 'depends_on' => ['a'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'd', 'type' => 'delay', 'name' => 'D', 'depends_on' => ['b'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'e', 'type' => 'delay', 'name' => 'E', 'depends_on' => ['b'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'f', 'type' => 'delay', 'name' => 'F', 'depends_on' => ['c'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'g', 'type' => 'condition', 'name' => 'G', 'depends_on' => ['d', 'e'], 'config' => ['expression' => 'true']],
            ['id' => 'h', 'type' => 'condition', 'name' => 'H', 'depends_on' => ['e', 'f'], 'config' => ['expression' => 'true']],
            ['id' => 'i', 'type' => 'condition', 'name' => 'I', 'depends_on' => ['g', 'h'], 'config' => ['expression' => 'true']],
        ];

        $result = $this->parser->validate($steps);

        expect($result['total_levels'])->toBe(5);
        expect($result['levels'][0])->toBe(['a']);
        expect($result['levels'][4])->toBe(['i']);
    });

    it('detects indirect cycle through multiple hops', function () {
        // A → B → C → D → B (cycle: B→C→D→B)
        $steps = [
            ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []]],
            ['id' => 'b', 'type' => 'delay', 'name' => 'B', 'depends_on' => ['a', 'd'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'c', 'type' => 'delay', 'name' => 'C', 'depends_on' => ['b'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'd', 'type' => 'delay', 'name' => 'D', 'depends_on' => ['c'], 'config' => ['duration_seconds' => 1]],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagCycleException::class);
    });

    it('handles single node DAG', function () {
        $steps = [
            ['id' => 'only_step', 'type' => 'http', 'name' => 'Solo', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []]],
        ];

        $result = $this->parser->validate($steps);

        expect($result['total_levels'])->toBe(1);
        expect($result['sorted'])->toHaveCount(1);
    });

    it('rejects step with multiple invalid dependencies', function () {
        $steps = [
            ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => ['ghost_1', 'ghost_2'], 'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []]],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'non-existent step');
    });
});

describe('DAG Parser - Config Validation Edge Cases', function () {

    it('rejects HTTP step with empty method', function () {
        $steps = [
            ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => ['method' => '', 'url' => 'https://x.com', 'headers' => []]],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class);
    });

    it('rejects delay exceeding 3600 seconds', function () {
        $steps = [
            ['id' => 'a', 'type' => 'delay', 'name' => 'A', 'depends_on' => [], 'config' => ['duration_seconds' => 3601]],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, '3600');
    });

    it('accepts valid retry with all fields', function () {
        $steps = [
            [
                'id' => 'a',
                'type' => 'http',
                'name' => 'A',
                'depends_on' => [],
                'config' => ['method' => 'POST', 'url' => 'https://api.example.com/data', 'headers' => []],
                'retry' => ['max_retries' => 5, 'backoff' => 'linear', 'initial_delay_ms' => 2000],
            ],
        ];

        $result = $this->parser->validate($steps);
        expect($result['sorted'])->toHaveCount(1);
    });

    it('rejects retry with invalid backoff type', function () {
        $steps = [
            [
                'id' => 'a',
                'type' => 'http',
                'name' => 'A',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []],
                'retry' => ['backoff' => 'fibonacci'],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'backoff');
    });

    it('rejects retry initial_delay_ms below 100', function () {
        $steps = [
            [
                'id' => 'a',
                'type' => 'http',
                'name' => 'A',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []],
                'retry' => ['initial_delay_ms' => 50],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'initial_delay_ms');
    });

    it('accepts condition with complex expression', function () {
        $steps = [
            ['id' => 'a', 'type' => 'condition', 'name' => 'A', 'depends_on' => [], 'config' => ['expression' => "previous.count >= 100"]],
        ];

        $result = $this->parser->validate($steps);
        expect($result['sorted'])->toHaveCount(1);
    });

    it('rejects condition with empty expression', function () {
        $steps = [
            ['id' => 'a', 'type' => 'condition', 'name' => 'A', 'depends_on' => [], 'config' => ['expression' => '   ']],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'expression');
    });
});

describe('DAG Parser - Execution Plan', function () {

    it('getExecutionPlan marks parallel levels correctly', function () {
        $steps = [
            ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []]],
            ['id' => 'b', 'type' => 'http', 'name' => 'B', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://y.com', 'headers' => []]],
            ['id' => 'c', 'type' => 'condition', 'name' => 'C', 'depends_on' => ['a', 'b'], 'config' => ['expression' => 'true']],
        ];

        $plan = $this->parser->getExecutionPlan($steps);

        expect($plan[0]['parallel'])->toBeTrue();  // Level 0 has 2 steps
        expect($plan[1]['parallel'])->toBeFalse(); // Level 1 has 1 step
    });

    it('getExecutionPlan for linear chain shows all levels non-parallel', function () {
        $steps = [
            ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []]],
            ['id' => 'b', 'type' => 'delay', 'name' => 'B', 'depends_on' => ['a'], 'config' => ['duration_seconds' => 5]],
            ['id' => 'c', 'type' => 'condition', 'name' => 'C', 'depends_on' => ['b'], 'config' => ['expression' => 'true']],
        ];

        $plan = $this->parser->getExecutionPlan($steps);

        foreach ($plan as $level) {
            expect($level['parallel'])->toBeFalse();
        }
    });
});
