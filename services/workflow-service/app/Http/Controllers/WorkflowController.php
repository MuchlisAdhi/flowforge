<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\WorkflowData;
use App\Exceptions\DagCycleException;
use App\Exceptions\DagValidationException;
use App\Http\Requests\CreateWorkflowRequest;
use App\Http\Requests\UpdateWorkflowRequest;
use App\Repositories\WorkflowRepository;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin controller — delegates all business logic to WorkflowService.
 * Responsibilities: extract request context, call service, format HTTP response.
 */
class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly WorkflowRepository $workflowRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $workflows = $this->workflowRepository->listForTenant(
            tenantId: $tenantId,
            search: $request->input('search'),
            status: $request->input('status'),
            sortBy: $request->input('sort_by', 'created_at'),
            sortDir: $request->input('sort_dir', 'desc'),
            perPage: (int) $request->input('per_page', 15),
        );

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
        $data = WorkflowData::fromRequest($request->validated());

        try {
            $workflow = $this->workflowService->create(
                data: $data,
                tenantId: $this->tenantId($request),
                userId: $this->userId($request),
            );
        } catch (DagValidationException|DagCycleException $e) {
            return response()->json([
                'message' => 'Invalid workflow definition',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Workflow created successfully',
            'data' => $workflow,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $workflow = $this->workflowRepository->findForTenant(
            $id,
            $this->tenantId($request),
            ['versions', 'latestVersion']
        );

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        return response()->json(['data' => $workflow]);
    }

    public function update(UpdateWorkflowRequest $request, string $id): JsonResponse
    {
        $workflow = $this->workflowRepository->findForTenant($id, $this->tenantId($request));

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        $data = WorkflowData::fromRequest(array_merge(
            ['name' => $workflow->name, 'description' => $workflow->description],
            $request->validated()
        ));

        try {
            $workflow = $this->workflowService->update($data, $workflow, $this->userId($request));
        } catch (DagValidationException|DagCycleException $e) {
            return response()->json([
                'message' => 'Invalid workflow definition',
                'error' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Workflow updated successfully',
            'data' => $workflow,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $workflow = $this->workflowRepository->findForTenant($id, $this->tenantId($request));

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        $this->workflowService->deactivate($workflow);

        return response()->json(['message' => 'Workflow deactivated successfully']);
    }

    public function rollback(Request $request, string $id, int $version): JsonResponse
    {
        $workflow = $this->workflowRepository->findForTenant($id, $this->tenantId($request));

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        $workflow = $this->workflowService->rollback($workflow, $version, $this->userId($request));

        return response()->json([
            'message' => "Rolled back to version {$version}",
            'data' => $workflow,
        ]);
    }

    public function trigger(Request $request, string $id): JsonResponse
    {
        $workflow = $this->workflowRepository->findForTenant(
            $id,
            $this->tenantId($request),
            ['latestVersion']
        );

        if (! $workflow) {
            return response()->json(['message' => 'Workflow not found'], 404);
        }

        try {
            $result = $this->workflowService->trigger(
                $workflow,
                $this->tenantId($request),
                $this->userId($request)
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Workflow triggered successfully',
            'data' => [
                'run_id' => $result->runId,
                'status' => $result->status,
                'workflow_id' => $result->workflowId,
                'version' => $result->version,
            ],
        ], 202);
    }

    // --- Request context extraction (avoids repetition) ---

    private function tenantId(Request $request): string
    {
        return $request->attributes->get('tenant_id');
    }

    private function userId(Request $request): string
    {
        return $request->attributes->get('auth_user')->id;
    }
}
