<?php

namespace Database\Seeders;

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder relies on identity-service seeding tenants first
        // This seeds example workflow data for the demo tenant
        $tenantId = '550e8400-e29b-41d4-a716-446655440000'; // Will match identity seeder

        $workflow = Workflow::create([
            'tenant_id' => $tenantId,
            'name' => 'Daily Student Sync',
            'description' => 'Synchronizes student data from external API every morning',
            'is_active' => true,
        ]);

        WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => [
                'steps' => [
                    [
                        'id' => 'fetch_students',
                        'type' => 'http',
                        'name' => 'Fetch Students',
                        'depends_on' => [],
                        'config' => [
                            'method' => 'GET',
                            'url' => 'https://api.example.com/students',
                            'headers' => ['Accept' => 'application/json'],
                        ],
                        'retry' => [
                            'max_retries' => 3,
                            'backoff' => 'exponential',
                            'initial_delay_ms' => 500,
                        ],
                    ],
                    [
                        'id' => 'validate_response',
                        'type' => 'condition',
                        'name' => 'Validate Response',
                        'depends_on' => ['fetch_students'],
                        'config' => [
                            'expression' => 'previous.status_code == 200',
                        ],
                    ],
                    [
                        'id' => 'wait_processing',
                        'type' => 'delay',
                        'name' => 'Wait for Processing',
                        'depends_on' => ['validate_response'],
                        'config' => [
                            'duration_seconds' => 5,
                        ],
                    ],
                    [
                        'id' => 'send_notification',
                        'type' => 'http',
                        'name' => 'Send Notification',
                        'depends_on' => ['wait_processing'],
                        'config' => [
                            'method' => 'POST',
                            'url' => 'https://webhook.example.com/notify',
                            'headers' => ['Content-Type' => 'application/json'],
                            'body' => ['message' => 'Student sync completed'],
                        ],
                    ],
                ],
                'execution_plan' => [
                    ['fetch_students'],
                    ['validate_response'],
                    ['wait_processing'],
                    ['send_notification'],
                ],
            ],
            'timeout_seconds' => 600,
            'change_note' => 'Initial version',
        ]);
    }
}
