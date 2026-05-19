<?php

return [
    'provider' => env('AI_PROVIDER', 'mock'),
    'base_url' => env('AI_BASE_URL', 'https://api.groq.com/openai/v1'),
    'api_key' => env('AI_API_KEY', ''),
    'model' => env('AI_MODEL', 'llama-3.3-70b-versatile'),
    'max_tokens' => (int) env('AI_MAX_TOKENS', 4096),
    'temperature' => (float) env('AI_TEMPERATURE', 0.2),
];
