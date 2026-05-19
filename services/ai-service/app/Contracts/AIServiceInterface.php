<?php

namespace App\Contracts;

interface AIServiceInterface
{
    /**
     * Generate a workflow DAG definition from a natural language prompt.
     *
     * @param string $prompt User's natural language workflow description
     * @param array $tenantContext Optional tenant-specific context
     * @return array Valid DAG definition
     * @throws \App\Exceptions\AIServiceException
     */
    public function generateWorkflowFromPrompt(string $prompt, array $tenantContext = []): array;

    /**
     * Analyze a workflow step failure and suggest fixes.
     *
     * @param array $context Failure context (step info, error, previous outputs)
     * @return array Analysis with diagnosis and suggestions
     * @throws \App\Exceptions\AIServiceException
     */
    public function analyzeFailure(array $context): array;
}
