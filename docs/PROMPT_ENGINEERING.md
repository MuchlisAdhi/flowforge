# Prompt Engineering — FlowForge AI Service

## Overview

FlowForge AI Service menggunakan LLM (default: Groq dengan model Llama 3.3 70B) untuk dua fitur utama:
1. **Natural Language Workflow Builder** — mengkonversi deskripsi bahasa natural menjadi DAG JSON
2. **Intelligent Failure Analysis** — menganalisis error context dan memberikan diagnosis

## Provider Architecture

```
┌─────────────────┐
│   AIService     │  ← Interface
├─────────────────┤
│ + generateWorkflowFromPrompt(prompt, tenant): array
│ + analyzeFailure(context): array
└────────┬────────┘
         │ implements
    ┌────┴──────────────────┐
    │                       │
┌───▼────────────┐  ┌──────▼──────────┐
│ GroqProvider   │  │ MockAIProvider  │
│ (Production)   │  │ (Development)   │
└────────────────┘  └─────────────────┘
```

## Prompt Design: Workflow Builder

### System Prompt

```
You are a workflow DAG generator for FlowForge platform. Your task is to convert
natural language workflow descriptions into valid JSON DAG definitions.

RULES:
1. Output ONLY valid JSON, no markdown, no explanations
2. Each step must have: id (snake_case), type, name, depends_on, config
3. Valid step types: "http", "delay", "condition", "script"
4. Step IDs must be unique and use snake_case
5. depends_on is an array of step IDs that must complete before this step
6. The DAG must be acyclic - no circular dependencies
7. First steps must have depends_on: []

STEP TYPE CONFIGS:
- http: { method, url, headers, body? }
- delay: { duration_seconds }
- condition: { expression }
- script: { command, args? }

RETRY CONFIG (optional per step):
{ max_retries: 1-5, backoff: "exponential"|"linear", initial_delay_ms: 100-5000 }
```

### User Prompt Template

```
Convert this workflow description to a DAG JSON:

"{user_input}"

Respond with ONLY the JSON object matching this schema:
{
  "name": "string",
  "description": "string",
  "timeout_seconds": number,
  "steps": [...]
}
```

### Token Limit Handling

1. **Input truncation**: User prompt dibatasi 2000 characters
2. **System prompt**: ~500 tokens (fixed)
3. **Max output tokens**: 4096 (configurable via ENV)
4. **Total context budget**: 8192 tokens (Groq limit varies by model)

```php
// Token estimation (rough: 1 token ≈ 4 characters for English)
$estimatedInputTokens = ceil(strlen($prompt) / 4);
$maxInputTokens = 2000; // ~8000 characters

if ($estimatedInputTokens > $maxInputTokens) {
    $prompt = substr($prompt, 0, $maxInputTokens * 4);
    // Add truncation note
    $prompt .= "\n[Note: Input was truncated to fit token limits]";
}
```

## Prompt Design: Failure Analysis

### System Prompt

```
You are a workflow execution failure analyst. Given the error context of a failed
workflow step, provide a concise diagnosis and actionable fix suggestion.

RULES:
1. Be concise - max 3 sentences for diagnosis
2. Provide exactly 1-3 actionable suggestions
3. Output JSON format only
4. Consider common failure modes: timeout, connection refused, auth expired,
   rate limited, invalid response, script error

OUTPUT FORMAT:
{
  "diagnosis": "string",
  "root_cause": "timeout|connection|auth|rate_limit|validation|script_error|unknown",
  "suggestions": ["string"],
  "confidence": 0.0-1.0
}
```

### Context Template

```
Workflow: {workflow_name}
Step: {step_name} (type: {step_type})
Error: {error_message}
HTTP Status: {status_code} (if applicable)
Duration: {duration_ms}ms
Retry Attempt: {attempt}/{max_retries}
Previous Step Output: {truncated_previous_output}
```

## Guardrails Against Malformed Output

### 1. JSON Validation Pipeline

```php
public function parseAIResponse(string $raw): array
{
    // Step 1: Extract JSON from response (handle markdown wrapping)
    $json = $this->extractJson($raw);
    
    // Step 2: Parse JSON
    $decoded = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new MalformedAIResponseException('Invalid JSON: ' . json_last_error_msg());
    }
    
    // Step 3: Validate against schema
    $this->validateSchema($decoded);
    
    // Step 4: Sanitize values
    return $this->sanitize($decoded);
}
```

### 2. JSON Extraction (handling LLM wrapping)

```php
private function extractJson(string $raw): string
{
    // Remove markdown code fences if present
    $raw = preg_replace('/^```(?:json)?\s*\n?/m', '', $raw);
    $raw = preg_replace('/\n?```\s*$/m', '', $raw);
    
    // Try to find JSON object in response
    if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
        return $matches[0];
    }
    
    throw new MalformedAIResponseException('No JSON object found in response');
}
```

### 3. Schema Validation

```php
private function validateWorkflowSchema(array $data): void
{
    $required = ['name', 'steps'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new ValidationException("Missing required field: {$field}");
        }
    }
    
    foreach ($data['steps'] as $i => $step) {
        $this->validateStep($step, $i);
    }
    
    // Validate DAG structure (no cycles, valid dependencies)
    $this->dagParser->validate($data['steps']);
}
```

### 4. Fallback Strategy

```
Attempt 1: Parse response normally
    ↓ (fail)
Attempt 2: Try to extract JSON from mixed content
    ↓ (fail)
Attempt 3: Retry LLM call with stricter prompt
    ↓ (fail)
Return error to user: "AI could not generate a valid workflow. Please try rephrasing."
```

## Testing Strategy

### Mock Provider Responses

MockAIProvider returns deterministic responses for testing:

```php
class MockAIProvider implements AIServiceInterface
{
    public function generateWorkflowFromPrompt(string $prompt, Tenant $tenant): array
    {
        return [
            'name' => 'Generated Workflow',
            'description' => 'Auto-generated from: ' . substr($prompt, 0, 50),
            'timeout_seconds' => 300,
            'steps' => [
                [
                    'id' => 'step_1',
                    'type' => 'http',
                    'name' => 'Fetch Data',
                    'depends_on' => [],
                    'config' => [
                        'method' => 'GET',
                        'url' => 'https://api.example.com/data',
                        'headers' => []
                    ]
                ],
                [
                    'id' => 'step_2',
                    'type' => 'condition',
                    'name' => 'Validate',
                    'depends_on' => ['step_1'],
                    'config' => [
                        'expression' => 'previous.status == "success"'
                    ]
                ]
            ]
        ];
    }
}
```

## Security Considerations

1. **No user PII in prompts** — only workflow descriptions
2. **API key stored in Secrets Manager** — never in code or logs
3. **Rate limiting on AI endpoints** — prevent abuse
4. **Output sanitization** — strip any executable content from AI responses
5. **Audit logging** — log all AI interactions (without full response for cost)
6. **User must review** — AI output is never auto-saved, always presented for confirmation

## Cost Optimization

- **Groq**: Significantly cheaper than OpenAI for inference
- **Caching**: Identical prompts cached in Redis (5 min TTL)
- **Token budgeting**: Strict limits prevent runaway costs
- **Mock for development**: Zero AI cost during development/testing
