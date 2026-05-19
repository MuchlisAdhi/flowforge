<?php

namespace App\Services\Providers;

use App\Contracts\AIServiceInterface;
use App\Exceptions\AIServiceException;
use App\Services\PromptTemplates;
use App\Services\ResponseParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqProvider implements AIServiceInterface
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private float $temperature;

    public function __construct()
    {
        $this->baseUrl = config('ai.base_url');
        $this->apiKey = config('ai.api_key');
        $this->model = config('ai.model');
        $this->maxTokens = (int) config('ai.max_tokens', 4096);
        $this->temperature = (float) config('ai.temperature', 0.2);

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('AI_API_KEY is not configured');
        }
    }

    public function generateWorkflowFromPrompt(string $prompt, array $tenantContext = []): array
    {
        // Truncate input to prevent token limit issues
        $prompt = $this->truncateInput($prompt, 2000);

        $systemPrompt = PromptTemplates::workflowBuilderSystem();
        $userPrompt = PromptTemplates::workflowBuilderUser($prompt);

        $response = $this->callApi($systemPrompt, $userPrompt);

        // Parse and validate response
        $parser = new ResponseParser();
        $parsed = $parser->parseWorkflowResponse($response);

        return $parsed;
    }

    public function analyzeFailure(array $context): array
    {
        $systemPrompt = PromptTemplates::failureAnalysisSystem();
        $userPrompt = PromptTemplates::failureAnalysisUser($context);

        $response = $this->callApi($systemPrompt, $userPrompt);

        $parser = new ResponseParser();
        return $parser->parseFailureAnalysisResponse($response);
    }

    private function callApi(string $systemPrompt, string $userPrompt): string
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'max_tokens' => $this->maxTokens,
                    'temperature' => $this->temperature,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if ($response->failed()) {
                Log::error('AI API request failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                throw AIServiceException::providerError("HTTP {$response->status()}");
            }

            $data = $response->json();
            return $data['choices'][0]['message']['content'] ?? '';

        } catch (AIServiceException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('AI API call error', ['error' => $e->getMessage()]);
            throw AIServiceException::providerError($e->getMessage());
        }
    }

    private function truncateInput(string $input, int $maxChars): string
    {
        if (strlen($input) <= $maxChars * 4) {
            return $input;
        }

        return substr($input, 0, $maxChars * 4) . "\n[Input truncated to fit token limits]";
    }
}
