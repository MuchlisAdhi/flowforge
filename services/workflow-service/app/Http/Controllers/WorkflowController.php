<?php

namespace App\Http\Controllers;

use App\Exceptions\DagCycleException;
use App\Exceptions\DagValidationException;
use App\Http\Requests\CreateWorkflowRequest;
use App\Http\Requests\UpdateWorkflowRequest;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Services\DagParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly DagParser $dagParser
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $query = Workflow::tenant($tenantId)
            ->with('latestVersion');

        // Filtering
        if ($request->has('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['name', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        // Pagination
        $perPage = min((int) $request->input('per_page', 15), 100);
        $workflows = $query->paginate($perPage);

        return response()->json([
            'data' => $workflows->items(),
            'meta' => [
                'current_page' => $workflows->currentPage(),
                'last_page' => $workflows->lastPage(),
                'per_page' => $workflows->perPage(),
                'total' => $workflows->total(),
            ],
        ]);
    }

    public function store(CreateWorkflowRequest $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        $userId = $request->attributes->get('auth_user')->id;
        $validated = $request->validated();

        // Validate DAG
        try {
            $dagResult = $this->dagParser->validate($validated['steps']);
        } catch (DagValidationException|DagCycleException $e) {
            return response()->json([
                'message' => 'Invalid workflow definition',
                'error' => $e->getMessage(),
            ], 422);
        }

        $workflow = DB::transaction(function () use ($tenantId, $userId, $validated, $dagResult) {
            $workflow = Workflow::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => true,
            ]);

            WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'version' => 1,
                'definition' => [
                    'steps' => $validated['steps'],
                    'execution_plan' => $dagResult['levels'],
                ],
                'timeout_seconds' => $validated['timeout_seconds'] ?? 300,
                'change_note' => 'Initial version',
                'created_by' => $userId,
            ]);

            return $workflow;
        });

        $workflow->load('latestVersion');

        return response()->json([
            'message' => 'Workflow created successfully',
            'data' => $workflow,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $workflow = Workflow::tenant($tenantId)
            ->with(['versions', 'latestVersion'])
            ->find($id);

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        return response()->json([
            'data' => $workflow,
        ]);
    }

    public function update(UpdateWorkflowRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        $userId = $request->attributes->get('auth_user')->id;
        $validated = $request->validated();

        $workflow = Workflow::tenant($tenantId)->find($id);

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        // If steps are provided, validate DAG and create new version
        if (isset($validated['steps'])) {
            try {
                $dagResult = $this->dagParser->validate($validated['steps']);
            } catch (DagValidationException|DagCycleException $e) {
                return response()->json([
                    'message' => 'Invalid workflow definition',
                    'error' => $e->getMessage(),
                ], 422);
            }

            DB::transaction(function () use ($workflow, $userId, $validated, $dagResult) {
                $workflow->update([
                    'name' => $validated['name'] ?? $workflow->name,
                    'description' => $validated['description'] ?? $workflow->description,
                ]);

                $latestVersion = $workflow->versions()->max('version') ?? 0;

                WorkflowVersion::create([
                    'workflow_id' => $workflow->id,
                    'version' => $latestVersion + 1,
                    'definition' => [
                        'steps' => $validated['steps'],
                        'execution_plan' => $dagResult['levels'],
                    ],
                    'timeout_seconds' => $validated['timeout_seconds'] ?? 300,
                    'change_note' => $validated['change_note'] ?? null,
                    'created_by' => $userId,
                ]);
            });
        } else {
            $workflow->update([
                'name' => $validated['name'] ?? $workflow->name,
                'description' => $validated['description'] ?? $workflow->description,
            ]);
        }

        $workflow->load('latestVersion');

        return response()->json([
            'message' => 'Workflow updated successfully',
            'data' => $workflow,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $workflow = Workflow::tenant($tenantId)->find($id);

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        // Soft-deactivate instead of hard delete to preserve history
        $workflow->update(['is_active' => false]);

        return response()->json([
            'message' => 'Workflow deactivated successfully',
        ]);
    }

    public function rollback(Request $request, string $id, int $version): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        $userId = $request->attributes->get('auth_user')->id;

        $workflow = Workflow::tenant($tenantId)->find($id);

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        $targetVersion = WorkflowVersion::where('workflow_id', $workflow->id)
            ->where('version', $version)
            ->first();

        if (! $targetVersion) {
            return response()->json(['message' => 'Version not found'], 404);
        }

        // Create new version with old definition (rollback = new version with old content)
        $latestVersion = $workflow->versions()->max('version');

        WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version' => $latestVersion + 1,
            'definition' => $targetVersion->definition,
            'timeout_seconds' => $targetVersion->timeout_seconds,
            'change_note' => "Rollback to version {$version}",
            'created_by' => $userId,
        ]);

        $workflow->load('latestVersion');

        return response()->json([
            'message' => "Rolled back to version {$version}",
            'data' => $workflow,
        ]);
    }

    public function trigger(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');
        $userId = $request->attributes->get('auth_user')->id;

        $workflow = Workflow::tenant($tenantId)->with('latestVersion')->find($id);

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        if (! $workflow->is_active) {
            return response()->json(['message' => 'Workflow is deactivated'], 422);
        }

        if (! $workflow->latestVersion) {
            return response()->json(['message' => 'Workflow has no version'], 422);
        }

        // Create run record
        $run = WorkflowRun::create([
            'tenant_id' => $tenantId,
            'workflow_id' => $workflow->id,
            'workflow_version_id' => $workflow->latestVersion->id,
            'status' => WorkflowRun::STATUS_PENDING,
            'trigger_type' => 'manual',
            'triggered_by' => $userId,
        ]);

        // Dispatch to execution service via RabbitMQ
        // In MVP, we publish a message that execution-service will consume
        dispatch(new \App\Jobs\TriggerWorkflowExecution($run));

        return response()->json([
            'message' => 'Workflow triggered successfully',
            'data' => [
                'run_id' => $run->id,
                'status' => $run->status,
                'workflow_id' => $workflow->id,
                'version' => $workflow->latestVersion->version,
            ],
        ], 202);
    }
}
