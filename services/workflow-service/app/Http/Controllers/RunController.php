<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\RunRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RunController extends Controller
{
    public function __construct(
        private readonly RunRepository $runRepository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $runs = $this->runRepository->listForTenant(
            tenantId: $tenantId,
            status: $request->input('status'),
            workflowId: $request->input('workflow_id'),
            from: $request->input('from'),
            to: $request->input('to'),
            sortBy: $request->input('sort_by', 'created_at'),
            sortDir: $request->input('sort_dir', 'desc'),
            perPage: (int) $request->input('per_page', 20),
        );

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

        $run = $this->runRepository->findForTenant(
            $id,
            $tenantId,
            ['workflow:id,name', 'version', 'stepRuns']
        );

        if (! $run) {
            return response()->json(['message' => 'Run not found'], 404);
        }

        return response()->json(['data' => $run]);
    }
}
