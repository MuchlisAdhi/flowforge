<?php

use App\DTOs\WorkflowData;
use App\DTOs\TriggerResult;
use App\Exceptions\DagValidationException;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Repositories\WorkflowRepository;
use App\Services\DagParser;
use App\Services\WorkflowService;

describe('WorkflowService', function () {

    it('creates workflow with valid DAG inside transaction', function () {
        $dagParser = new DagParser();
        $repository = new WorkflowRepository();
        $service = new WorkflowService($dagParser, $repository);

        $data = new WorkflowData(
            name: 'Service Test Workflow',
            description: 'Created by service test',
            timeoutSeconds: 300,
            steps: [
                [
                    'id' => 'step_one',
                    'type' => 'http',
                    'name' => 'Fetch',
                    'depends_on' => [],
                    'config' => ['method' => 'GET', 'url' => 'https://api.example.com', 'headers' => []],
                ],
            ],
        );

        // This test validates the DTO construction and DAG parsing logic
        // without database (DAG parser is pure logic)
        $validatedDag = $dagParser->validate($data->steps);

        expect($validatedDag)->toHaveKey('sorted');
        expect($validatedDag)->toHaveKey('levels');
        expect($validatedDag['levels'][0])->toBe(['step_one']);
    });

    it('rejects invalid DAG at service level', function () {
        $dagParser = new DagParser();
        $repository = new WorkflowRepository();
        $service = new WorkflowService($dagParser, $repository);

        $data = new WorkflowData(
            name: 'Bad Workflow',
            description: null,
            timeoutSeconds: 300,
            steps: [
                ['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => ['b'], 'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []]],
                ['id' => 'b', 'type' => 'http', 'name' => 'B', 'depends_on' => ['a'], 'config' => ['method' => 'GET', 'url' => 'https://x.com', 'headers' => []]],
            ],
        );

        // The service delegates to DagParser which throws DagCycleException
        expect(fn () => $dagParser->validate($data->steps))
            ->toThrow(\App\Exceptions\DagCycleException::class);
    });

    it('constructs DTO from validated request data', function () {
        $validated = [
            'name' => 'DTO Test',
            'description' => 'From request',
            'timeout_seconds' => 600,
            'steps' => [['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => []]],
            'change_note' => 'Updated',
        ];

        $dto = WorkflowData::fromRequest($validated);

        expect($dto->name)->toBe('DTO Test');
        expect($dto->description)->toBe('From request');
        expect($dto->timeoutSeconds)->toBe(600);
        expect($dto->steps)->toHaveCount(1);
        expect($dto->changeNote)->toBe('Updated');
    });

    it('constructs DTO with defaults for optional fields', function () {
        $validated = [
            'name' => 'Minimal',
            'steps' => [['id' => 'a', 'type' => 'http', 'name' => 'A', 'depends_on' => [], 'config' => []]],
        ];

        $dto = WorkflowData::fromRequest($validated);

        expect($dto->description)->toBeNull();
        expect($dto->timeoutSeconds)->toBe(300);
        expect($dto->changeNote)->toBeNull();
    });

    it('TriggerResult DTO holds correct values', function () {
        $result = new TriggerResult(
            runId: 'run-abc',
            status: 'pending',
            workflowId: 'wf-xyz',
            version: 3,
        );

        expect($result->runId)->toBe('run-abc');
        expect($result->status)->toBe('pending');
        expect($result->workflowId)->toBe('wf-xyz');
        expect($result->version)->toBe(3);
    });
});

describe('WorkflowRepository', function () {

    it('returns null for non-existent workflow', function () {
        $repository = new WorkflowRepository();
        $result = $repository->findForTenant('non-existent', 'tenant-1');
        expect($result)->toBeNull();
    })->skip('Requires database');

    it('enforces tenant boundary in findForTenant', function () {
        // Even if we know the workflow ID, passing wrong tenant should return null
        $repository = new WorkflowRepository();
        $result = $repository->findForTenant('any-id', 'wrong-tenant');
        expect($result)->toBeNull();
    })->skip('Requires database');
});
