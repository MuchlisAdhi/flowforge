<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\TriggerResult;
use App\DTOs\WorkflowData;
use App\Exceptions\DagCycleException;
use App\Exceptions\DagValidationException;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use App\Repositories\WorkflowRepository;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates workflow business logic.
 *
 * Responsibilities:
 * - Coordinate between DAG validation and persistence
 * - Enforce business rules (active state, version existence)
 * - Wrap multi-model operations in transactions
 * - Dispatch async jobs for execution
 *
 * Does NOT handle:
 * - HTTP concerns (status codes, response format)
 * - Authentication/Authorization (middleware responsibility)
 */
class WorkflowService
{
    public function __construct(
        private readonly DagParser $dagParser,
        private readonly WorkflowRepository $repository,
    ) {}

    /**
     * Create a new workflow with its first version.
     *
     * @throws DagValidationException|DagCycleException
     */
    public function create(WorkflowData $data, string $tenantId, string $userId): Workflow
    {
        $dagResult = $this->dagParser->validate($data->steps);

        return DB::transaction(function () use ($data, $tenantId, $userId, $dagResult): Workflow {
            $workflow = Workflow::create([
                'tenant_id' => $tenantId,
                'name' => $data->name,
                'description' => $data->description,
                'is_active' => true,
            ]);

            WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'version' => 1,
                'definition' => [
                    'steps' => $data->steps,
                    'execution_plan' => $dagResult['levels'],
                ],
                'timeout_seconds' => $data->timeoutSeconds,
                'change_note' => 'Initial version',
                'created_by' => $userId,
            ]);

            return $workflow->load('latestVersion');
        });
    }

    /**
     * Update workflow metadata and optionally create a new version.
     *
     * @throws DagValidationException|DagCycleException
     */
    public function update(WorkflowData $data, Workflow $workflow, string $userId): Workflow
    {
        // If steps are provided, validate and create a new version
        if (! empty($data->steps)) {
            $dagResult = $this->dagParser->validate($data->steps);

            return DB::transaction(function () use ($data, $workflow, $userId, $dagResult): Workflow {
                $workflow->update([
                    'name' => $data->name,
                    'description' => $data->description,
                ]);

                $nextVersion = $this->repository->getLatestVersionNumber($workflow->id) + 1;

                WorkflowVersion::create([
                    'workflow_id' => $workflow->id,
                    'version' => $nextVersion,
                    'definition' => [
                        'steps' => $data->steps,
                        'execution_plan' => $dagResult['levels'],
                    ],
                    'timeout_seconds' => $data->timeoutSeconds,
                    'change_note' => $data->changeNote,
                    'created_by' => $userId,
                ]);

                return $workflow->load('latestVersion');
            });
        }

        // Metadata-only update (no new version)
        $workflow->update([
            'name' => $data->name,
            'description' => $data->description,
        ]);

        return $workflow->load('latestVersion');
    }

    /**
     * Deactivate a workflow (soft delete).
     */
    public function deactivate(Workflow $workflow): void
    {
        $workflow->update(['is_active' => false]);
    }

    /**
     * Rollback to a specific version by creating a new version with the old definition.
     */
    public function rollback(Workflow $workflow, int $targetVersion, string $userId): Workflow
    {
        $version = $this->repository->getVersion($workflow->id, $targetVersion);

        if (! $version) {
            abort(404, 'Version not found');
        }

        $nextVersion = $this->repository->getLatestVersionNumber($workflow->id) + 1;

        WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version' => $nextVersion,
            'definition' => $version->definition,
            'timeout_seconds' => $version->timeout_seconds,
            'change_note' => "Rollback to version {$targetVersion}",
            'created_by' => $userId,
        ]);

        return $workflow->load('latestVersion');
    }

    /**
     * Trigger workflow execution.
     * Creates a run record and dispatches to execution queue.
     *
     * @throws \DomainException If workflow cannot be triggered
     */
    public function trigger(Workflow $workflow, string $tenantId, string $userId): TriggerResult
    {
        if (! $workflow->is_active) {
            throw new \DomainException('Cannot trigger a deactivated workflow');
        }

        $latestVersion = $workflow->latestVersion;

        if (! $latestVersion) {
            throw new \DomainException('Workflow has no version to execute');
        }

        // Create the run within a transaction to ensure atomicity
        $run = DB::transaction(function () use ($workflow, $latestVersion, $tenantId, $userId): WorkflowRun {
            return WorkflowRun::create([
                'tenant_id' => $tenantId,
                'workflow_id' => $workflow->id,
                'workflow_version_id' => $latestVersion->id,
                'status' => WorkflowRun::STATUS_PENDING,
                'trigger_type' => 'manual',
                'triggered_by' => $userId,
            ]);
        });

        // Dispatch to execution service via RabbitMQ queue
        dispatch(new \App\Jobs\TriggerWorkflowExecution($run));

        return new TriggerResult(
            runId: $run->id,
            status: $run->status,
            workflowId: $workflow->id,
            version: $latestVersion->version,
        );
    }
}
