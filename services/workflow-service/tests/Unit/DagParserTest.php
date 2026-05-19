<?php

use App\Exceptions\DagCycleException;
use App\Exceptions\DagValidationException;
use App\Services\DagParser;

beforeEach(function () {
    $this->parser = new DagParser();
});

describe('DAG Validation', function () {

    it('validates a simple linear DAG', function () {
        $steps = [
            [
                'id' => 'step_one',
                'type' => 'http',
                'name' => 'Fetch Data',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/data', 'headers' => []],
            ],
            [
                'id' => 'step_two',
                'type' => 'condition',
                'name' => 'Check Result',
                'depends_on' => ['step_one'],
                'config' => ['expression' => 'previous.status_code == 200'],
            ],
        ];

        $result = $this->parser->validate($steps);

        expect($result)->toHaveKeys(['sorted', 'levels', 'total_levels']);
        expect($result['total_levels'])->toBe(2);
        expect($result['levels'][0])->toBe(['step_one']);
        expect($result['levels'][1])->toBe(['step_two']);
    });

    it('validates a parallel DAG', function () {
        $steps = [
            [
                'id' => 'fetch_a',
                'type' => 'http',
                'name' => 'Fetch A',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
            ],
            [
                'id' => 'fetch_b',
                'type' => 'http',
                'name' => 'Fetch B',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/b', 'headers' => []],
            ],
            [
                'id' => 'merge',
                'type' => 'condition',
                'name' => 'Merge Results',
                'depends_on' => ['fetch_a', 'fetch_b'],
                'config' => ['expression' => 'true'],
            ],
        ];

        $result = $this->parser->validate($steps);

        expect($result['total_levels'])->toBe(2);
        // Level 0 should have both parallel steps
        expect($result['levels'][0])->toContain('fetch_a');
        expect($result['levels'][0])->toContain('fetch_b');
        // Level 1 should have merge
        expect($result['levels'][1])->toBe(['merge']);
    });

    it('detects a cycle in the DAG', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'http',
                'name' => 'Step A',
                'depends_on' => ['step_c'],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
            ],
            [
                'id' => 'step_b',
                'type' => 'http',
                'name' => 'Step B',
                'depends_on' => ['step_a'],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/b', 'headers' => []],
            ],
            [
                'id' => 'step_c',
                'type' => 'http',
                'name' => 'Step C',
                'depends_on' => ['step_b'],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/c', 'headers' => []],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagCycleException::class);
    });

    it('detects self-dependency', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'http',
                'name' => 'Step A',
                'depends_on' => ['step_a'],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'cannot depend on itself');
    });

    it('rejects empty steps array', function () {
        expect(fn () => $this->parser->validate([]))
            ->toThrow(DagValidationException::class, 'at least one step');
    });

    it('rejects duplicate step IDs', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'http',
                'name' => 'Step A',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
            ],
            [
                'id' => 'step_a',
                'type' => 'http',
                'name' => 'Step A duplicate',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'Duplicate step IDs');
    });

    it('rejects invalid depends_on reference', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'http',
                'name' => 'Step A',
                'depends_on' => ['nonexistent_step'],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'non-existent step');
    });

    it('rejects invalid step type', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'invalid_type',
                'name' => 'Step A',
                'depends_on' => [],
                'config' => [],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'type');
    });

    it('rejects step missing id', function () {
        $steps = [
            [
                'type' => 'http',
                'name' => 'Step A',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'id');
    });

    it('rejects invalid step ID format', function () {
        $steps = [
            [
                'id' => 'Step-With-Invalid-Format',
                'type' => 'http',
                'name' => 'Step',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'snake_case');
    });

    it('validates http config requires valid URL', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'http',
                'name' => 'Step A',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'not-a-url', 'headers' => []],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'url');
    });

    it('validates delay config requires positive duration', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'delay',
                'name' => 'Wait',
                'depends_on' => [],
                'config' => ['duration_seconds' => 0],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'duration_seconds');
    });

    it('validates script config only allows whitelisted commands', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'script',
                'name' => 'Bad Script',
                'depends_on' => [],
                'config' => ['command' => 'rm -rf /'],
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'allowed commands');
    });

    it('validates retry configuration', function () {
        $steps = [
            [
                'id' => 'step_a',
                'type' => 'http',
                'name' => 'Step A',
                'depends_on' => [],
                'config' => ['method' => 'GET', 'url' => 'https://api.example.com/a', 'headers' => []],
                'retry' => ['max_retries' => 20], // exceeds max of 10
            ],
        ];

        expect(fn () => $this->parser->validate($steps))
            ->toThrow(DagValidationException::class, 'max_retries');
    });
});

describe('Topological Sort', function () {

    it('sorts a diamond DAG correctly', function () {
        //     A
        //    / \
        //   B   C
        //    \ /
        //     D
        $steps = [
            ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://api.example.com', 'headers' => []]],
            ['id' => 'b', 'type' => 'delay', 'name' => 'B', 'depends_on' => ['a'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'c', 'type' => 'delay', 'name' => 'C', 'depends_on' => ['a'], 'config' => ['duration_seconds' => 1]],
            ['id' => 'd', 'type' => 'condition', 'name' => 'D', 'depends_on' => ['b', 'c'], 'config' => ['expression' => 'true']],
        ];

        $result = $this->parser->validate($steps);

        expect($result['total_levels'])->toBe(3);
        expect($result['levels'][0])->toBe(['a']);
        expect($result['levels'][1])->toContain('b');
        expect($result['levels'][1])->toContain('c');
        expect($result['levels'][2])->toBe(['d']);
    });

    it('returns execution plan', function () {
        $steps = [
            ['id' => 'fetch', 'type' => 'http', 'name' => 'Fetch', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://api.example.com', 'headers' => []]],
            ['id' => 'process', 'type' => 'condition', 'name' => 'Process', 'depends_on' => ['fetch'], 'config' => ['expression' => 'true']],
        ];

        $plan = $this->parser->getExecutionPlan($steps);

        expect($plan)->toHaveCount(2);
        expect($plan[0]['level'])->toBe(0);
        expect($plan[0]['parallel'])->toBeFalse();
        expect($plan[0]['steps'])->toBe(['fetch']);
    });
});
