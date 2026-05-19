<?php

use App\Services\PromptTemplates;

describe('PromptTemplates', function () {

    it('generates system prompt for workflow builder', function () {
        $prompt = PromptTemplates::workflowBuilderSystem();

        expect($prompt)->toBeString();
        expect($prompt)->toContain('DAG');
        expect($prompt)->toContain('http');
        expect($prompt)->toContain('delay');
        expect($prompt)->toContain('condition');
        expect($prompt)->toContain('script');
        expect($prompt)->toContain('depends_on');
        expect($prompt)->toContain('snake_case');
    });

    it('generates user prompt with input embedded', function () {
        $input = 'Fetch data from API and send notification';
        $prompt = PromptTemplates::workflowBuilderUser($input);

        expect($prompt)->toContain($input);
        expect($prompt)->toContain('JSON');
    });

    it('generates system prompt for failure analysis', function () {
        $prompt = PromptTemplates::failureAnalysisSystem();

        expect($prompt)->toBeString();
        expect($prompt)->toContain('diagnosis');
        expect($prompt)->toContain('root_cause');
        expect($prompt)->toContain('suggestions');
        expect($prompt)->toContain('confidence');
    });

    it('generates failure analysis user prompt with context', function () {
        $context = [
            'workflow_name' => 'Student Sync',
            'step_name' => 'Fetch API',
            'step_type' => 'http',
            'error_message' => 'Connection timeout after 30s',
            'status_code' => null,
            'duration_ms' => 30000,
            'attempt' => 3,
            'max_retries' => 3,
        ];

        $prompt = PromptTemplates::failureAnalysisUser($context);

        expect($prompt)->toContain('Student Sync');
        expect($prompt)->toContain('Fetch API');
        expect($prompt)->toContain('http');
        expect($prompt)->toContain('Connection timeout');
        expect($prompt)->toContain('30000');
        expect($prompt)->toContain('3/3');
    });

    it('truncates long error messages in failure context', function () {
        $longError = str_repeat('Error detail. ', 100);
        $context = [
            'workflow_name' => 'Test',
            'step_name' => 'Step',
            'step_type' => 'http',
            'error_message' => $longError,
        ];

        $prompt = PromptTemplates::failureAnalysisUser($context);

        // Should be truncated to 500 chars
        expect(strlen($prompt))->toBeLessThan(strlen($longError));
    });
});
