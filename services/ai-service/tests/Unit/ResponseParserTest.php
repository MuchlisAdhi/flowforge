<?php

use App\Exceptions\AIServiceException;
use App\Services\ResponseParser;

beforeEach(function () {
    $this->parser = new ResponseParser();
});

describe('Workflow Response Parsing', function () {

    it('parses valid JSON workflow response', function () {
        $raw = json_encode([
            'name' => 'Test Workflow',
            'description' => 'A test',
            'timeout_seconds' => 300,
            'steps' => [
                [
                    'id' => 'step_1',
                    'type' => 'http',
                    'name' => 'Fetch',
                    'depends_on' => [],
                    'config' => ['method' => 'GET', 'url' => 'https://api.test.com'],
                ],
            ],
        ]);

        $result = $this->parser->parseWorkflowResponse($raw);

        expect($result['name'])->toBe('Test Workflow');
        expect($result['steps'])->toHaveCount(1);
        expect($result['steps'][0]['id'])->toBe('step_1');
    });

    it('handles markdown-wrapped JSON', function () {
        $raw = "```json\n" . json_encode([
            'name' => 'Wrapped',
            'steps' => [
                ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => ['method' => 'GET', 'url' => 'https://x.com']],
            ],
        ]) . "\n```";

        $result = $this->parser->parseWorkflowResponse($raw);
        expect($result['name'])->toBe('Wrapped');
    });

    it('throws on invalid JSON', function () {
        expect(fn () => $this->parser->parseWorkflowResponse('not json at all'))
            ->toThrow(AIServiceException::class);
    });

    it('throws on missing required fields', function () {
        $raw = json_encode(['description' => 'No name or steps']);

        expect(fn () => $this->parser->parseWorkflowResponse($raw))
            ->toThrow(AIServiceException::class, 'Missing required field');
    });

    it('throws on empty steps', function () {
        $raw = json_encode(['name' => 'Test', 'steps' => []]);

        expect(fn () => $this->parser->parseWorkflowResponse($raw))
            ->toThrow(AIServiceException::class, 'non-empty array');
    });

    it('sanitizes step IDs to snake_case', function () {
        $raw = json_encode([
            'name' => 'Test',
            'steps' => [
                ['id' => 'Step-With-Dashes', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => []],
            ],
        ]);

        $result = $this->parser->parseWorkflowResponse($raw);
        expect($result['steps'][0]['id'])->toBe('step_with_dashes');
    });

    it('clamps timeout_seconds to valid range', function () {
        $raw = json_encode([
            'name' => 'Test',
            'timeout_seconds' => 999999,
            'steps' => [
                ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => []],
            ],
        ]);

        $result = $this->parser->parseWorkflowResponse($raw);
        expect($result['timeout_seconds'])->toBe(86400); // max
    });

    it('detects cycles in generated workflow', function () {
        $raw = json_encode([
            'name' => 'Cyclic',
            'steps' => [
                ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => ['b'], 'config' => []],
                ['id' => 'b', 'type' => 'http', 'name' => 'B', 'depends_on' => ['a'], 'config' => []],
            ],
        ]);

        expect(fn () => $this->parser->parseWorkflowResponse($raw))
            ->toThrow(AIServiceException::class, 'circular dependencies');
    });
});

describe('Failure Analysis Response Parsing', function () {

    it('parses valid failure analysis response', function () {
        $raw = json_encode([
            'diagnosis' => 'Connection timed out to target service.',
            'root_cause' => 'timeout',
            'suggestions' => ['Increase timeout', 'Check service health'],
            'confidence' => 0.85,
        ]);

        $result = $this->parser->parseFailureAnalysisResponse($raw);

        expect($result['diagnosis'])->toBe('Connection timed out to target service.');
        expect($result['root_cause'])->toBe('timeout');
        expect($result['suggestions'])->toHaveCount(2);
        expect($result['confidence'])->toBe(0.85);
    });

    it('normalizes unknown root cause to "unknown"', function () {
        $raw = json_encode([
            'diagnosis' => 'Something went wrong.',
            'root_cause' => 'cosmic_ray',
            'suggestions' => ['Try again'],
            'confidence' => 0.5,
        ]);

        $result = $this->parser->parseFailureAnalysisResponse($raw);
        expect($result['root_cause'])->toBe('unknown');
    });

    it('clamps confidence to 0-1 range', function () {
        $raw = json_encode([
            'diagnosis' => 'Test.',
            'root_cause' => 'timeout',
            'suggestions' => ['Fix it'],
            'confidence' => 5.0,
        ]);

        $result = $this->parser->parseFailureAnalysisResponse($raw);
        expect($result['confidence'])->toBe(1.0);
    });
});
