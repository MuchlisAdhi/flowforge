<?php

namespace App\Services\Providers;

use App\Contracts\AIServiceInterface;

/**
 * Mock AI provider for development and testing.
 * Returns deterministic responses without calling external APIs.
 */
class MockAIProvider implements AIServiceInterface
{
    public function generateWorkflowFromPrompt(string $prompt, array $tenantContext = []): array
    {
        // Generate a realistic-looking workflow based on keywords in the prompt
        $steps = $this->generateStepsFromPrompt($prompt);

        return [
            'name' => $this->generateName($prompt),
            'description' => 'Auto-generated workflow from: ' . substr($prompt, 0, 100),
            'timeout_seconds' => 300,
            'steps' => $steps,
        ];
    }

    public function analyzeFailure(array $context): array
    {
        $errorMessage = $context['error_message'] ?? 'Unknown error';
        $stepType = $context['step_type'] ?? 'unknown';

        return [
            'diagnosis' => $this->generateDiagnosis($errorMessage, $stepType),
            'root_cause' => $this->classifyRootCause($errorMessage),
            'suggestions' => $this->generateSuggestions($errorMessage, $stepType),
            'confidence' => 0.75,
        ];
    }

    private function generateStepsFromPrompt(string $prompt): array
    {
        $steps = [];
        $promptLower = strtolower($prompt);

        // Try to extract URL from prompt
        $fetchUrl = 'https://jsonplaceholder.typicode.com/posts/1';
        if (preg_match('/https?:\/\/[^\s,)]+/', $prompt, $matches)) {
            $fetchUrl = $matches[0];
            // Clean trailing punctuation
            $fetchUrl = rtrim($fetchUrl, '.,;:!)');
        }

        // Step 1: Always start with a data fetch
        $steps[] = [
            'id' => 'fetch_data',
            'type' => 'http',
            'name' => 'Fetch Data',
            'depends_on' => [],
            'config' => [
                'method' => 'GET',
                'url' => $fetchUrl,
                'headers' => ['Accept' => 'application/json'],
            ],
            'retry' => [
                'max_retries' => 3,
                'backoff' => 'exponential',
                'initial_delay_ms' => 1000,
            ],
        ];

        // Step 2: Validation/condition
        if (str_contains($promptLower, 'validasi') || str_contains($promptLower, 'cek') || str_contains($promptLower, 'check')) {
            $steps[] = [
                'id' => 'validate_data',
                'type' => 'condition',
                'name' => 'Validate Data',
                'depends_on' => ['fetch_data'],
                'config' => [
                    'expression' => 'previous.status_code == 200',
                ],
            ];
        }

        // Step 3: If there's a delay/wait mentioned
        if (str_contains($promptLower, 'tunggu') || str_contains($promptLower, 'wait') || str_contains($promptLower, 'delay')) {
            $steps[] = [
                'id' => 'wait_period',
                'type' => 'delay',
                'name' => 'Wait Period',
                'depends_on' => [end($steps)['id']],
                'config' => [
                    'duration_seconds' => 10,
                ],
            ];
        }

        // Step 4: If there's a send/notify/webhook action
        if (str_contains($promptLower, 'kirim') || str_contains($promptLower, 'send') || str_contains($promptLower, 'webhook') || str_contains($promptLower, 'notif') || str_contains($promptLower, 'alert')) {
            $steps[] = [
                'id' => 'send_result',
                'type' => 'http',
                'name' => 'Send Results',
                'depends_on' => [end($steps)['id']],
                'config' => [
                    'method' => 'POST',
                    'url' => 'https://httpbin.org/post',
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => ['status' => 'completed', 'source' => 'flowforge'],
                ],
                'retry' => [
                    'max_retries' => 2,
                    'backoff' => 'exponential',
                    'initial_delay_ms' => 500,
                ],
            ];
        }

        // Ensure at least 2 steps
        if (count($steps) < 2) {
            $steps[] = [
                'id' => 'process_result',
                'type' => 'condition',
                'name' => 'Check Result',
                'depends_on' => ['fetch_data'],
                'config' => [
                    'expression' => 'previous.status_code == 200',
                ],
            ];
        }

        return $steps;
    }

    private function generateName(string $prompt): string
    {
        $words = array_slice(explode(' ', $prompt), 0, 5);
        $name = implode(' ', $words);

        return strlen($name) > 50 ? substr($name, 0, 50) : $name;
    }

    private function generateDiagnosis(string $error, string $stepType): string
    {
        if (str_contains(strtolower($error), 'timeout')) {
            return 'The step timed out waiting for a response. This typically indicates the target service is under heavy load or unreachable.';
        }

        if (str_contains(strtolower($error), 'connection')) {
            return 'Connection to the target service failed. The service may be down or there may be a network configuration issue.';
        }

        if (str_contains(strtolower($error), '401') || str_contains(strtolower($error), 'auth')) {
            return 'Authentication failed. The API credentials may have expired or been revoked.';
        }

        return "The {$stepType} step failed with an unexpected error. Review the error message and step configuration.";
    }

    private function classifyRootCause(string $error): string
    {
        $errorLower = strtolower($error);

        if (str_contains($errorLower, 'timeout')) return 'timeout';
        if (str_contains($errorLower, 'connection')) return 'connection';
        if (str_contains($errorLower, '401') || str_contains($errorLower, 'auth')) return 'auth';
        if (str_contains($errorLower, '429') || str_contains($errorLower, 'rate')) return 'rate_limit';
        if (str_contains($errorLower, 'validation')) return 'validation';

        return 'unknown';
    }

    private function generateSuggestions(string $error, string $stepType): array
    {
        $suggestions = [];
        $errorLower = strtolower($error);

        if (str_contains($errorLower, 'timeout')) {
            $suggestions[] = 'Increase the step timeout configuration';
            $suggestions[] = 'Check if the target service is responding normally';
            $suggestions[] = 'Add retry with exponential backoff';
        } elseif (str_contains($errorLower, 'connection')) {
            $suggestions[] = 'Verify the target URL is correct and accessible';
            $suggestions[] = 'Check network connectivity and firewall rules';
        } elseif (str_contains($errorLower, '401') || str_contains($errorLower, 'auth')) {
            $suggestions[] = 'Refresh or rotate the API credentials';
            $suggestions[] = 'Verify the authentication headers are correctly configured';
        } else {
            $suggestions[] = 'Review the step configuration for errors';
            $suggestions[] = 'Check the target service logs for more details';
            $suggestions[] = 'Consider adding retry logic if the error is transient';
        }

        return $suggestions;
    }
}
