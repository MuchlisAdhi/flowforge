<?php

namespace App\Http\Controllers;

use App\Contracts\AIServiceInterface;
use App\Exceptions\AIServiceException;
use App\Services\AIServiceFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function workflowBuilder(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $prompt = $request->input('prompt');
        $tenantId = $request->attributes->get('tenant_id');

        try {
            $aiService = AIServiceFactory::make();
            $result = $aiService->generateWorkflowFromPrompt($prompt, [
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'message' => 'Workflow generated successfully. Please review before saving.',
                'data' => $result,
                'requires_review' => true,
            ]);

        } catch (AIServiceException $e) {
            return response()->json([
                'message' => 'AI could not generate a valid workflow',
                'error' => $e->getMessage(),
                'suggestion' => 'Try rephrasing your description with more specific details about the steps.',
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => 'Internal service error',
            ], 500);
        }
    }

    public function failureAnalysis(Request $request): JsonResponse
    {
        $request->validate([
            'workflow_name' => ['required', 'string'],
            'step_name' => ['required', 'string'],
            'step_type' => ['required', 'string'],
            'error_message' => ['required', 'string', 'max:2000'],
            'status_code' => ['nullable', 'integer'],
            'duration_ms' => ['nullable', 'integer'],
            'attempt' => ['nullable', 'integer'],
            'max_retries' => ['nullable', 'integer'],
        ]);

        $context = $request->only([
            'workflow_name', 'step_name', 'step_type',
            'error_message', 'status_code', 'duration_ms',
            'attempt', 'max_retries',
        ]);

        try {
            $aiService = AIServiceFactory::make();
            $result = $aiService->analyzeFailure($context);

            return response()->json([
                'message' => 'Failure analysis completed',
                'data' => $result,
            ]);

        } catch (AIServiceException $e) {
            return response()->json([
                'message' => 'AI could not analyze the failure',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => 'Internal service error',
            ], 500);
        }
    }
}
