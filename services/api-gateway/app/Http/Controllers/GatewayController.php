<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * API Gateway forwards requests to internal services.
 * Handles auth validation and request routing.
 */
class GatewayController extends Controller
{
    private array $serviceMap;

    public function __construct()
    {
        $this->serviceMap = [
            'identity' => config('services.identity_url', 'http://identity-service:8001'),
            'workflow' => config('services.workflow_url', 'http://workflow-service:8002'),
            'execution' => config('services.execution_url', 'http://execution-service:8003'),
            'ai' => config('services.ai_url', 'http://ai-service:8004'),
        ];
    }

    // --- Auth routes (forwarded to identity-service) ---

    public function login(Request $request): JsonResponse
    {
        return $this->forward('identity', '/api/auth/login', $request);
    }

    public function register(Request $request): JsonResponse
    {
        return $this->forward('identity', '/api/auth/register', $request);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->forward('identity', '/api/auth/me', $request);
    }

    // --- Workflow routes (forwarded to workflow-service) ---

    public function workflowIndex(Request $request): JsonResponse
    {
        return $this->forward('workflow', '/api/workflows', $request);
    }

    public function workflowStore(Request $request): JsonResponse
    {
        return $this->forward('workflow', '/api/workflows', $request, 'POST');
    }

    public function workflowShow(Request $request, string $id): JsonResponse
    {
        return $this->forward('workflow', "/api/workflows/{$id}", $request);
    }

    public function workflowUpdate(Request $request, string $id): JsonResponse
    {
        return $this->forward('workflow', "/api/workflows/{$id}", $request, 'PUT');
    }

    public function workflowDestroy(Request $request, string $id): JsonResponse
    {
        return $this->forward('workflow', "/api/workflows/{$id}", $request, 'DELETE');
    }

    public function workflowRollback(Request $request, string $id, int $version): JsonResponse
    {
        return $this->forward('workflow', "/api/workflows/{$id}/versions/{$version}/rollback", $request, 'POST');
    }

    public function workflowTrigger(Request $request, string $id): JsonResponse
    {
        return $this->forward('workflow', "/api/workflows/{$id}/trigger", $request, 'POST');
    }

    // --- Run routes ---

    public function runIndex(Request $request): JsonResponse
    {
        return $this->forward('workflow', '/api/runs', $request);
    }

    public function runShow(Request $request, string $id): JsonResponse
    {
        return $this->forward('workflow', "/api/runs/{$id}", $request);
    }

    // --- Health routes ---

    public function healthMetrics(Request $request): JsonResponse
    {
        return $this->forward('workflow', '/api/health/metrics', $request);
    }

    // --- AI routes ---

    public function aiWorkflowBuilder(Request $request): JsonResponse
    {
        return $this->forward('ai', '/api/ai/workflow-builder', $request, 'POST');
    }

    public function aiFailureAnalysis(Request $request): JsonResponse
    {
        return $this->forward('ai', '/api/ai/failure-analysis', $request, 'POST');
    }

    // --- SSE endpoint ---

    public function sseStream(Request $request)
    {
        $tenantId = $request->attributes->get('tenant_id');

        return response()->stream(function () use ($tenantId) {
            $redis = app('redis');
            $channel = "flowforge:execution:{$tenantId}";

            // Send initial connection event
            echo "event: connected\n";
            echo "data: {\"status\": \"connected\", \"channel\": \"{$channel}\"}\n\n";
            ob_flush();
            flush();

            // Subscribe and stream events
            $redis->subscribe([$channel], function ($message) {
                echo "event: execution\n";
                echo "data: {$message}\n\n";
                ob_flush();
                flush();
            });
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // --- Internal forwarding ---

    private function forward(string $service, string $path, Request $request, ?string $method = null): JsonResponse
    {
        $baseUrl = $this->serviceMap[$service] ?? null;

        if (! $baseUrl) {
            return response()->json(['message' => 'Service not available'], 503);
        }

        $url = $baseUrl . $path;
        $method = $method ?? $request->method();

        try {
            $httpRequest = Http::timeout(30)
                ->withHeaders($this->buildForwardHeaders($request));

            // Include query params for GET requests
            if ($method === 'GET' || $method === null) {
                $url .= '?' . http_build_query($request->query());
            }

            $response = match (strtoupper($method)) {
                'GET' => $httpRequest->get($url),
                'POST' => $httpRequest->post($url, $request->all()),
                'PUT' => $httpRequest->put($url, $request->all()),
                'PATCH' => $httpRequest->patch($url, $request->all()),
                'DELETE' => $httpRequest->delete($url),
                default => $httpRequest->get($url),
            };

            return response()->json(
                $response->json() ?? ['message' => 'No response body'],
                $response->status()
            );

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json([
                'message' => 'Service unavailable',
                'service' => $service,
            ], 503);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gateway error',
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    private function buildForwardHeaders(Request $request): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        // Forward auth header
        if ($request->hasHeader('Authorization')) {
            $headers['Authorization'] = $request->header('Authorization');
        }

        // Forward request ID for tracing
        $headers['X-Request-ID'] = $request->header('X-Request-ID', (string) str()->uuid());

        return $headers;
    }
}
