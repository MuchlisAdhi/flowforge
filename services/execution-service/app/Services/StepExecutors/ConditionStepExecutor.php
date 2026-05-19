<?php

namespace App\Services\StepExecutors;

use RuntimeException;

/**
 * Evaluates a simple condition expression.
 * Supports basic comparisons against previous step outputs.
 */
class ConditionStepExecutor implements StepExecutorInterface
{
    public function execute(array $config, array $context = []): ?array
    {
        $expression = $config['expression'] ?? '';

        if (empty($expression)) {
            throw new RuntimeException('Condition step: expression is required');
        }

        $result = $this->evaluate($expression, $context);

        if (! $result) {
            throw new RuntimeException("Condition '{$expression}' evaluated to false");
        }

        return [
            'expression' => $expression,
            'result' => $result,
            'evaluated_at' => now()->toISOString(),
        ];
    }

    /**
     * Simple expression evaluator.
     * Supports patterns like:
     * - "previous.status == 'success'"
     * - "previous.status_code == 200"
     * - "previous.body != ''"
     */
    private function evaluate(string $expression, array $context): bool
    {
        $previousOutputs = $context['previous_outputs'] ?? [];

        // Simple pattern matching for safe expression evaluation
        // Pattern: previous.field operator value
        if (preg_match('/^previous\.(\w+)\s*(==|!=|>=|<=|>|<)\s*[\'"]?(.+?)[\'"]?$/', $expression, $matches)) {
            $field = $matches[1];
            $operator = $matches[2];
            $expectedValue = $matches[3];

            // Get the actual value from the most recent previous output
            $actualValue = null;
            foreach ($previousOutputs as $output) {
                if (is_array($output) && isset($output[$field])) {
                    $actualValue = $output[$field];
                }
            }

            return $this->compare($actualValue, $operator, $expectedValue);
        }

        // If expression doesn't match known patterns, treat as truthy check
        // "true" always passes, anything else fails
        return strtolower(trim($expression)) === 'true';
    }

    private function compare(mixed $actual, string $operator, string $expected): bool
    {
        // Type coercion for numeric comparisons
        if (is_numeric($expected)) {
            $expected = (float) $expected;
            $actual = is_numeric($actual) ? (float) $actual : $actual;
        }

        return match ($operator) {
            '==' => $actual == $expected,
            '!=' => $actual != $expected,
            '>' => $actual > $expected,
            '<' => $actual < $expected,
            '>=' => $actual >= $expected,
            '<=' => $actual <= $expected,
            default => false,
        };
    }
}
