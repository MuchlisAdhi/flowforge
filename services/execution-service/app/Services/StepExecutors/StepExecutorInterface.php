<?php

namespace App\Services\StepExecutors;

interface StepExecutorInterface
{
    /**
     * Execute a step with the given configuration.
     *
     * @param array $config Step-specific configuration
     * @param array $context Execution context (previous outputs, run info)
     * @return array|null Output data from execution
     * @throws \RuntimeException If execution fails
     */
    public function execute(array $config, array $context = []): ?array;
}
