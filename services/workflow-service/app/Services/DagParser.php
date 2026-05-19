<?php

namespace App\Services;

use App\Exceptions\DagCycleException;
use App\Exceptions\DagValidationException;

class DagParser
{
    private const VALID_STEP_TYPES = ['http', 'script', 'delay', 'condition'];
    private const VALID_BACKOFF_TYPES = ['exponential', 'linear'];

    /**
     * Validate and parse a DAG definition.
     *
     * @param array $steps Raw step definitions
     * @return array Validated and topologically sorted steps with execution levels
     * @throws DagValidationException|DagCycleException
     */
    public function validate(array $steps): array
    {
        if (empty($steps)) {
            throw new DagValidationException('Workflow must have at least one step');
        }

        $this->validateStepStructure($steps);
        $this->validateUniqueIds($steps);
        $this->validateDependencies($steps);
        $this->detectCycles($steps);

        return $this->topologicalSort($steps);
    }

    /**
     * Validate each step has required fields and valid types.
     */
    private function validateStepStructure(array $steps): void
    {
        foreach ($steps as $index => $step) {
            $prefix = "Step [{$index}]";

            if (! isset($step['id']) || ! is_string($step['id']) || trim($step['id']) === '') {
                throw new DagValidationException("{$prefix}: 'id' is required and must be a non-empty string");
            }

            if (! preg_match('/^[a-z][a-z0-9_]*$/', $step['id'])) {
                throw new DagValidationException("{$prefix}: 'id' must be snake_case (lowercase, numbers, underscores, start with letter)");
            }

            if (! isset($step['type']) || ! in_array($step['type'], self::VALID_STEP_TYPES)) {
                throw new DagValidationException(
                    "{$prefix} ({$step['id']}): 'type' must be one of: " . implode(', ', self::VALID_STEP_TYPES)
                );
            }

            if (! isset($step['name']) || ! is_string($step['name'])) {
                throw new DagValidationException("{$prefix} ({$step['id']}): 'name' is required");
            }

            if (! isset($step['depends_on']) || ! is_array($step['depends_on'])) {
                throw new DagValidationException("{$prefix} ({$step['id']}): 'depends_on' must be an array");
            }

            if (! isset($step['config']) || ! is_array($step['config'])) {
                throw new DagValidationException("{$prefix} ({$step['id']}): 'config' is required");
            }

            $this->validateStepConfig($step);
            $this->validateRetryConfig($step);
        }
    }

    /**
     * Validate step-specific config.
     */
    private function validateStepConfig(array $step): void
    {
        $id = $step['id'];
        $config = $step['config'];

        match ($step['type']) {
            'http' => $this->validateHttpConfig($id, $config),
            'script' => $this->validateScriptConfig($id, $config),
            'delay' => $this->validateDelayConfig($id, $config),
            'condition' => $this->validateConditionConfig($id, $config),
        };
    }

    private function validateHttpConfig(string $id, array $config): void
    {
        if (! isset($config['method']) || ! in_array(strtoupper($config['method']), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
            throw new DagValidationException("Step '{$id}': http config requires valid 'method' (GET, POST, PUT, PATCH, DELETE)");
        }

        if (! isset($config['url']) || ! filter_var($config['url'], FILTER_VALIDATE_URL)) {
            throw new DagValidationException("Step '{$id}': http config requires valid 'url'");
        }
    }

    private function validateScriptConfig(string $id, array $config): void
    {
        if (! isset($config['command']) || ! is_string($config['command'])) {
            throw new DagValidationException("Step '{$id}': script config requires 'command' string");
        }

        // Only allow predefined safe commands
        $allowedCommands = ['data-sync', 'report-generate', 'cache-clear', 'export-csv', 'import-data', 'send-notification'];
        if (! in_array($config['command'], $allowedCommands)) {
            throw new DagValidationException(
                "Step '{$id}': script command must be one of: " . implode(', ', $allowedCommands)
            );
        }
    }

    private function validateDelayConfig(string $id, array $config): void
    {
        if (! isset($config['duration_seconds']) || ! is_numeric($config['duration_seconds']) || $config['duration_seconds'] < 1) {
            throw new DagValidationException("Step '{$id}': delay config requires 'duration_seconds' (positive integer)");
        }

        if ($config['duration_seconds'] > 3600) {
            throw new DagValidationException("Step '{$id}': delay duration cannot exceed 3600 seconds");
        }
    }

    private function validateConditionConfig(string $id, array $config): void
    {
        if (! isset($config['expression']) || ! is_string($config['expression']) || trim($config['expression']) === '') {
            throw new DagValidationException("Step '{$id}': condition config requires non-empty 'expression'");
        }
    }

    /**
     * Validate retry configuration if provided.
     */
    private function validateRetryConfig(array $step): void
    {
        if (! isset($step['retry'])) {
            return;
        }

        $retry = $step['retry'];
        $id = $step['id'];

        if (isset($retry['max_retries'])) {
            if (! is_int($retry['max_retries']) || $retry['max_retries'] < 0 || $retry['max_retries'] > 10) {
                throw new DagValidationException("Step '{$id}': retry.max_retries must be 0-10");
            }
        }

        if (isset($retry['backoff'])) {
            if (! in_array($retry['backoff'], self::VALID_BACKOFF_TYPES)) {
                throw new DagValidationException(
                    "Step '{$id}': retry.backoff must be: " . implode(', ', self::VALID_BACKOFF_TYPES)
                );
            }
        }

        if (isset($retry['initial_delay_ms'])) {
            if (! is_int($retry['initial_delay_ms']) || $retry['initial_delay_ms'] < 100 || $retry['initial_delay_ms'] > 60000) {
                throw new DagValidationException("Step '{$id}': retry.initial_delay_ms must be 100-60000");
            }
        }
    }

    /**
     * Ensure all step IDs are unique.
     */
    private function validateUniqueIds(array $steps): void
    {
        $ids = array_column($steps, 'id');
        $duplicates = array_diff_assoc($ids, array_unique($ids));

        if (! empty($duplicates)) {
            throw new DagValidationException(
                'Duplicate step IDs found: ' . implode(', ', array_unique($duplicates))
            );
        }
    }

    /**
     * Validate all depends_on references point to existing steps.
     */
    private function validateDependencies(array $steps): void
    {
        $validIds = array_column($steps, 'id');

        foreach ($steps as $step) {
            foreach ($step['depends_on'] as $dependency) {
                if (! in_array($dependency, $validIds)) {
                    throw new DagValidationException(
                        "Step '{$step['id']}': depends on non-existent step '{$dependency}'"
                    );
                }

                if ($dependency === $step['id']) {
                    throw new DagValidationException(
                        "Step '{$step['id']}': cannot depend on itself"
                    );
                }
            }
        }
    }

    /**
     * Detect cycles using DFS (Depth-First Search).
     *
     * @throws DagCycleException
     */
    private function detectCycles(array $steps): void
    {
        $graph = $this->buildAdjacencyList($steps);
        $visited = [];
        $recursionStack = [];

        foreach (array_column($steps, 'id') as $nodeId) {
            if ($this->hasCycleDFS($nodeId, $graph, $visited, $recursionStack)) {
                throw new DagCycleException(
                    'Cycle detected in workflow DAG. Check dependencies for circular references.'
                );
            }
        }
    }

    private function hasCycleDFS(string $node, array $graph, array &$visited, array &$recursionStack): bool
    {
        if (isset($recursionStack[$node])) {
            return true;
        }

        if (isset($visited[$node])) {
            return false;
        }

        $visited[$node] = true;
        $recursionStack[$node] = true;

        foreach ($graph[$node] ?? [] as $neighbor) {
            if ($this->hasCycleDFS($neighbor, $graph, $visited, $recursionStack)) {
                return true;
            }
        }

        unset($recursionStack[$node]);

        return false;
    }

    /**
     * Build adjacency list from steps (node → [nodes it depends on]).
     * For cycle detection, we build: node → [nodes that depend on it].
     */
    private function buildAdjacencyList(array $steps): array
    {
        $graph = [];

        foreach ($steps as $step) {
            $graph[$step['id']] = $step['depends_on'];
        }

        return $graph;
    }

    /**
     * Perform topological sort using Kahn's algorithm.
     * Returns steps grouped by execution level (parallel groups).
     *
     * @return array{sorted: array, levels: array<int, array>}
     */
    public function topologicalSort(array $steps): array
    {
        $stepMap = [];
        $inDegree = [];
        $adjacency = []; // step → [steps that depend on it]

        foreach ($steps as $step) {
            $stepMap[$step['id']] = $step;
            $inDegree[$step['id']] = count($step['depends_on']);
            $adjacency[$step['id']] = [];
        }

        // Build reverse adjacency: for each dependency, record who depends on it
        foreach ($steps as $step) {
            foreach ($step['depends_on'] as $dep) {
                $adjacency[$dep][] = $step['id'];
            }
        }

        // Kahn's algorithm with level tracking
        $queue = [];
        $levels = [];
        $sorted = [];
        $currentLevel = 0;

        // Start with nodes that have no dependencies (in-degree 0)
        foreach ($inDegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }

        while (! empty($queue)) {
            $levels[$currentLevel] = $queue;
            $nextQueue = [];

            foreach ($queue as $nodeId) {
                $sorted[] = $stepMap[$nodeId];

                // Reduce in-degree of dependent nodes
                foreach ($adjacency[$nodeId] as $dependent) {
                    $inDegree[$dependent]--;
                    if ($inDegree[$dependent] === 0) {
                        $nextQueue[] = $dependent;
                    }
                }
            }

            $queue = $nextQueue;
            $currentLevel++;
        }

        // If sorted count != step count, there's a cycle (shouldn't happen if detectCycles passed)
        if (count($sorted) !== count($steps)) {
            throw new DagCycleException('Cycle detected during topological sort');
        }

        return [
            'sorted' => $sorted,
            'levels' => $levels,
            'total_levels' => $currentLevel,
        ];
    }

    /**
     * Get execution plan summary for display purposes.
     */
    public function getExecutionPlan(array $steps): array
    {
        $result = $this->topologicalSort($steps);
        $plan = [];

        foreach ($result['levels'] as $level => $stepIds) {
            $plan[] = [
                'level' => $level,
                'parallel' => count($stepIds) > 1,
                'steps' => $stepIds,
            ];
        }

        return $plan;
    }
}
