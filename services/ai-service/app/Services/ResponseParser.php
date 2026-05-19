<?php

namespace App\Services;

use App\Exceptions\AIServiceException;

/**
 * Parses and validates AI responses with guardrails against malformed output.
 */
class ResponseParser
{
    /**
     * Parse workflow generation response from AI.
     */
    public function parseWorkflowResponse(string $raw): array
    {
        $json = $this->extractJson($raw);
        $data = $this->decodeJson($json);

        // Validate required fields
        $this->requireFields($data, ['name', 'steps']);

        if (! is_array($data['steps']) || empty($data['steps'])) {
            throw AIServiceException::validationFailed('steps must be a non-empty array');
        }

        // Validate each step structure
        foreach ($data['steps'] as $i => $step) {
            $this->validateStepStructure($step, $i);
        }

        // Validate no cycles (basic check)
        $this->validateNoCycles($data['steps']);

        // Sanitize and normalize
        return [
            'name' => substr(strip_tags($data['name']), 0, 255),
            'description' => substr(strip_tags($data['description'] ?? ''), 0, 2000),
            'timeout_seconds' => $this->clampInt($data['timeout_seconds'] ?? 300, 10, 86400),
            'steps' => array_map([$this, 'sanitizeStep'], $data['steps']),
        ];
    }

    /**
     * Parse failure analysis response from AI.
     */
    public function parseFailureAnalysisResponse(string $raw): array
    {
        $json = $this->extractJson($raw);
        $data = $this->decodeJson($json);

        $this->requireFields($data, ['diagnosis', 'root_cause', 'suggestions']);

        $validCauses = ['timeout', 'connection', 'auth', 'rate_limit', 'validation', 'script_error', 'unknown'];

        return [
            'diagnosis' => substr(strip_tags($data['diagnosis']), 0, 1000),
            'root_cause' => in_array($data['root_cause'], $validCauses) ? $data['root_cause'] : 'unknown',
            'suggestions' => array_map(
                fn ($s) => substr(strip_tags($s), 0, 500),
                array_slice((array) $data['suggestions'], 0, 5)
            ),
            'confidence' => $this->clampFloat($data['confidence'] ?? 0.5, 0.0, 1.0),
        ];
    }

    /**
     * Extract JSON from potentially wrapped response (handles markdown code blocks).
     */
    private function extractJson(string $raw): string
    {
        $raw = trim($raw);

        // Remove markdown code fences
        $raw = preg_replace('/^```(?:json)?\s*\n?/m', '', $raw);
        $raw = preg_replace('/\n?```\s*$/m', '', $raw);
        $raw = trim($raw);

        // Try to find JSON object
        if (preg_match('/(\{[\s\S]*\})/', $raw, $matches)) {
            return $matches[1];
        }

        throw AIServiceException::malformedResponse('No JSON object found in AI response');
    }

    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw AIServiceException::malformedResponse('Invalid JSON: ' . json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw AIServiceException::malformedResponse('Response is not a JSON object');
        }

        return $decoded;
    }

    private function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (! isset($data[$field])) {
                throw AIServiceException::validationFailed("Missing required field: {$field}");
            }
        }
    }

    private function validateStepStructure(array $step, int $index): void
    {
        $required = ['id', 'type', 'name', 'depends_on', 'config'];
        foreach ($required as $field) {
            if (! isset($step[$field])) {
                throw AIServiceException::validationFailed("Step [{$index}] missing field: {$field}");
            }
        }

        $validTypes = ['http', 'delay', 'condition', 'script'];
        if (! in_array($step['type'], $validTypes)) {
            throw AIServiceException::validationFailed("Step [{$index}] has invalid type: {$step['type']}");
        }
    }

    private function validateNoCycles(array $steps): void
    {
        $ids = array_column($steps, 'id');
        $graph = [];

        foreach ($steps as $step) {
            $graph[$step['id']] = $step['depends_on'] ?? [];
        }

        $visited = [];
        $stack = [];

        foreach ($ids as $id) {
            if ($this->hasCycle($id, $graph, $visited, $stack)) {
                throw AIServiceException::validationFailed('Generated workflow contains circular dependencies');
            }
        }
    }

    private function hasCycle(string $node, array $graph, array &$visited, array &$stack): bool
    {
        if (isset($stack[$node])) return true;
        if (isset($visited[$node])) return false;

        $visited[$node] = true;
        $stack[$node] = true;

        foreach ($graph[$node] ?? [] as $dep) {
            if (isset($graph[$dep]) && $this->hasCycle($dep, $graph, $visited, $stack)) {
                return true;
            }
        }

        unset($stack[$node]);
        return false;
    }

    private function sanitizeStep(array $step): array
    {
        return [
            'id' => preg_replace('/[^a-z0-9_]/', '_', strtolower($step['id'])),
            'type' => $step['type'],
            'name' => substr(strip_tags($step['name']), 0, 255),
            'depends_on' => array_values(array_filter(
                (array) $step['depends_on'],
                fn ($d) => is_string($d)
            )),
            'config' => $step['config'] ?? [],
            'retry' => $step['retry'] ?? null,
        ];
    }

    private function clampInt(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    private function clampFloat(mixed $value, float $min, float $max): float
    {
        return max($min, min($max, (float) $value));
    }
}
