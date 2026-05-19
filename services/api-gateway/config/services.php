<?php

return [
    'identity_url' => env('IDENTITY_SERVICE_URL', 'http://identity-service:8001'),
    'workflow_url' => env('WORKFLOW_SERVICE_URL', 'http://workflow-service:8002'),
    'execution_url' => env('EXECUTION_SERVICE_URL', 'http://execution-service:8003'),
    'ai_url' => env('AI_SERVICE_URL', 'http://ai-service:8004'),
];
