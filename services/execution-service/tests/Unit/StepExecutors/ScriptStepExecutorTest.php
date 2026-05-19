<?php

use App\Services\StepExecutors\ScriptStepExecutor;

beforeEach(function () {
    $this->executor = new ScriptStepExecutor();
});

it('executes allowed command successfully', function () {
    $config = ['command' => 'data-sync', 'args' => ['--source', 'test']];

    $result = $this->executor->execute($config, []);

    expect($result['command'])->toBe('data-sync');
    expect($result['status'])->toBe('completed');
    expect($result['duration_ms'])->toBeGreaterThan(0);
});

it('rejects disallowed command', function () {
    $config = ['command' => 'rm -rf /'];

    expect(fn () => $this->executor->execute($config, []))
        ->toThrow(RuntimeException::class, 'not in the allowed commands list');
});

it('rejects empty command', function () {
    $config = ['command' => ''];

    expect(fn () => $this->executor->execute($config, []))
        ->toThrow(RuntimeException::class, 'not in the allowed commands list');
});

it('returns all allowed commands', function () {
    $commands = ScriptStepExecutor::getAllowedCommands();

    expect($commands)->toBeArray();
    expect($commands)->toHaveKey('data-sync');
    expect($commands)->toHaveKey('cache-clear');
    expect($commands)->toHaveKey('export-csv');
});
