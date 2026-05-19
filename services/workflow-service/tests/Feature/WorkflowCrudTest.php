<?php

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature');

function generateToken(string $userId, string $tenantId, string $role = 'editor'): string
{
    return JWT::encode([
        'iss' => 'http://localhost',
        'sub' => $userId,
        'iat' => time(),
        'exp' => time() + 3600,
        'tenant_id' => $tenantId,
        'role' => $role,
        'email' => 'test@flowforge.local',
    ], config('jwt.secret'), 'HS256');
}

function validWorkflowPayload(): array
{
    return [
        'name' => 'Test Workflow',
        'description' => 'A test workflow',
        'timeout_seconds' => 300,
        'steps' => [
            [
                'id' => 'fetch_data',
                'type' => 'http',
                'name' => 'Fetch Data',
                'depends_on' => [],
                'config' => [
                    'method' => 'GET',
                    'url' => 'https://api.example.com/data',
                    'headers' => [],
                ],
                'retry' => [
                    'max_retries' => 3,
                    'backoff' => 'exponential',
                    'initial_delay_ms' => 1000,
                ],
            ],
            [
                'id' => 'check_result',
                'type' => 'condition',
                'name' => 'Validate',
                'depends_on' => ['fetch_data'],
                'config' => [
                    'expression' => "previous.status_code == 200",
                ],
            ],
        ],
    ];
}

describe('Workflow CRUD', function () {

    it('creates a workflow with valid DAG', function () {
        $token = generateToken('user-1', 'tenant-1', 'editor');

        $response = $this->postJson('/api/workflows', validWorkflowPayload(), [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'name', 'tenant_id', 'is_active'],
            ]);

        $this->assertDatabaseHas('workflows', [
            'tenant_id' => 'tenant-1',
            'name' => 'Test Workflow',
        ]);
    });

    it('rejects workflow with cycle in DAG', function () {
        $token = generateToken('user-1', 'tenant-1', 'editor');

        $payload = [
            'name' => 'Cyclic Workflow',
            'steps' => [
                [
                    'id' => 'step_a',
                    'type' => 'http',
                    'name' => 'A',
                    'depends_on' => ['step_b'],
                    'config' => ['method' => 'GET', 'url' => 'https://api.example.com', 'headers' => []],
                ],
                [
                    'id' => 'step_b',
                    'type' => 'http',
                    'name' => 'B',
                    'depends_on' => ['step_a'],
                    'config' => ['method' => 'GET', 'url' => 'https://api.example.com', 'headers' => []],
                ],
            ],
        ];

        $response = $this->postJson('/api/workflows', $payload, [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(422);
    });

    it('enforces tenant isolation on read', function () {
        $token1 = generateToken('user-1', 'tenant-1');
        $token2 = generateToken('user-2', 'tenant-2');

        // Create workflow as tenant-1
        Workflow::create([
            'id' => 'wf-1',
            'tenant_id' => 'tenant-1',
            'name' => 'Tenant 1 Workflow',
            'is_active' => true,
        ]);

        // Tenant-2 should not see tenant-1's workflow
        $response = $this->getJson('/api/workflows', [
            'Authorization' => "Bearer {$token2}",
        ]);

        $response->assertStatus(200);
        expect($response->json('data'))->toBeEmpty();
    });

    it('enforces tenant isolation on show', function () {
        $token2 = generateToken('user-2', 'tenant-2');

        Workflow::create([
            'id' => 'wf-1',
            'tenant_id' => 'tenant-1',
            'name' => 'Secret Workflow',
            'is_active' => true,
        ]);

        // Tenant-2 cannot access tenant-1's workflow by ID
        $response = $this->getJson('/api/workflows/wf-1', [
            'Authorization' => "Bearer {$token2}",
        ]);

        $response->assertStatus(404);
    });

    it('denies viewer from creating workflows', function () {
        $token = generateToken('user-1', 'tenant-1', 'viewer');

        $response = $this->postJson('/api/workflows', validWorkflowPayload(), [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(403);
    });

    it('allows admin to delete workflow', function () {
        $token = generateToken('user-1', 'tenant-1', 'admin');

        $workflow = Workflow::create([
            'id' => 'wf-del',
            'tenant_id' => 'tenant-1',
            'name' => 'To Delete',
            'is_active' => true,
        ]);

        $response = $this->deleteJson('/api/workflows/wf-del', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200);
        expect(Workflow::find('wf-del')->is_active)->toBeFalse();
    });

    it('returns 401 without token', function () {
        $response = $this->getJson('/api/workflows');
        $response->assertStatus(401);
    });
});

describe('Workflow Versioning', function () {

    it('creates a new version on update with steps', function () {
        $token = generateToken('user-1', 'tenant-1', 'editor');

        // Create initial workflow
        $workflow = Workflow::create([
            'id' => 'wf-ver',
            'tenant_id' => 'tenant-1',
            'name' => 'Versioned',
            'is_active' => true,
        ]);

        WorkflowVersion::create([
            'workflow_id' => 'wf-ver',
            'version' => 1,
            'definition' => ['steps' => validWorkflowPayload()['steps'], 'execution_plan' => []],
            'timeout_seconds' => 300,
            'created_by' => 'user-1',
        ]);

        // Update with new steps
        $newPayload = validWorkflowPayload();
        $newPayload['change_note'] = 'Added new step';

        $response = $this->putJson('/api/workflows/wf-ver', $newPayload, [
            'Authorization' => "Bearer {$token}",
        ]);

        $response->assertStatus(200);

        $versions = WorkflowVersion::where('workflow_id', 'wf-ver')->count();
        expect($versions)->toBe(2);
    });
});
