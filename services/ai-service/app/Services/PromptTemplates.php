<?php

namespace App\Services;

class PromptTemplates
{
    public static function workflowBuilderSystem(): string
    {
        return <<<'PROMPT'
You are a workflow DAG generator for FlowForge platform. Your task is to convert natural language workflow descriptions into valid JSON DAG definitions.

RULES:
1. Output ONLY valid JSON, no markdown, no explanations
2. Each step must have: id (snake_case), type, name, depends_on, config
3. Valid step types: "http", "delay", "condition", "script"
4. Step IDs must be unique and use snake_case
5. depends_on is an array of step IDs that must complete before this step
6. The DAG must be acyclic - no circular dependencies
7. First steps must have depends_on: []

STEP TYPE CONFIGS:
- http: { "method": "GET|POST|PUT|DELETE", "url": "https://...", "headers": {}, "body": {} }
- delay: { "duration_seconds": 1-3600 }
- condition: { "expression": "previous.field operator value" }
- script: { "command": "data-sync|report-generate|cache-clear|export-csv|import-data|send-notification" }

RETRY CONFIG (optional per step):
{ "max_retries": 1-5, "backoff": "exponential"|"linear", "initial_delay_ms": 100-5000 }

OUTPUT SCHEMA:
{
  "name": "string (max 255 chars)",
  "description": "string (max 2000 chars)",
  "timeout_seconds": number (10-86400),
  "steps": [
    {
      "id": "snake_case_id",
      "type": "http|delay|condition|script",
      "name": "Human readable name",
      "depends_on": ["step_id_1"],
      "config": { ... },
      "retry": { "max_retries": 3, "backoff": "exponential", "initial_delay_ms": 1000 }
    }
  ]
}
PROMPT;
    }

    public static function workflowBuilderUser(string $prompt): string
    {
        return <<<PROMPT
Convert this workflow description to a DAG JSON definition:

"{$prompt}"

Respond with ONLY the JSON object. No markdown code fences. No explanations.
PROMPT;
    }

    public static function failureAnalysisSystem(): string
    {
        return <<<'PROMPT'
You are a workflow execution failure analyst. Given the error context of a failed workflow step, provide a concise diagnosis and actionable fix suggestion.

RULES:
1. Be concise - max 3 sentences for diagnosis
2. Provide exactly 1-3 actionable suggestions
3. Output JSON format only
4. Consider common failure modes: timeout, connection refused, auth expired, rate limited, invalid response, script error

OUTPUT FORMAT:
{
  "diagnosis": "string (max 3 sentences)",
  "root_cause": "timeout|connection|auth|rate_limit|validation|script_error|unknown",
  "suggestions": ["string", "string"],
  "confidence": 0.0-1.0
}
PROMPT;
    }

    public static function failureAnalysisUser(array $context): string
    {
        $workflowName = $context['workflow_name'] ?? 'Unknown';
        $stepName = $context['step_name'] ?? 'Unknown';
        $stepType = $context['step_type'] ?? 'Unknown';
        $errorMessage = substr($context['error_message'] ?? 'No error message', 0, 500);
        $statusCode = $context['status_code'] ?? 'N/A';
        $duration = $context['duration_ms'] ?? 'N/A';
        $attempt = $context['attempt'] ?? 1;
        $maxRetries = $context['max_retries'] ?? 0;

        return <<<PROMPT
Workflow: {$workflowName}
Step: {$stepName} (type: {$stepType})
Error: {$errorMessage}
HTTP Status: {$statusCode}
Duration: {$duration}ms
Retry Attempt: {$attempt}/{$maxRetries}

Respond with ONLY the JSON object. No markdown code fences.
PROMPT;
    }
}
