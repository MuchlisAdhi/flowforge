<?php

namespace App\Http\Controllers;

use App\Models\WorkflowRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $query = WorkflowRun::tenant($tenantId)
            ->with(['workflow:id,name', 'version:id,version']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by workflow
        if ($request->has('workflow_id')) {
            $query->where('workflow_id', $request->input('workflow_id'));
        }

        // Date range filter
        if ($request->has('from')) {
            $query->where('created_at', '>=', $request->input('from'));
        }
        if ($request->has('to')) {
            $query->where('created_at', '<=', $request->input('to'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $allowedSorts = ['created_at', 'started_at', 'completed_at', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $runs = $query->paginate($perPage);

        return response()->json([
            'data' => $runs->items(),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $run = WorkflowRun::tenant($tenantId)
            ->with(['workflow:id,name', 'version', 'stepRuns'])
            ->find($id);

        if (! $run) {
            return response()->json(['message' => 'Run not found'], 404);
        }

        return response()->json([
            'data' => $run,
        ]);
    }
}
