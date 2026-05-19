<?php

use App\Services\StepExecutors\ConditionStepExecutor;

beforeEach(function () {
    $this->executor = new ConditionStepExecutor();
});

it('evaluates equality expression with previous output', function () {
    $config = ['expression' => "previous.status_code == 200"];
    $context = [
        'previous_outputs' => [
            'fetch_data' => ['status_code' => 200, 'body' => '{}'],
        ],
    ];

    $result = $this->executor->execute($config, $context);

    expect($result['result'])->toBeTrue();
    expect($result['expression'])->toBe("previous.status_code == 200");
});

it('evaluates inequality expression', function () {
    $config = ['expression' => "previous.status != error"];
    $context = [
        'previous_outputs' => [
            'step_1' => ['status' => 'success'],
        ],
    ];

    $result = $this->executor->execute($config, $context);
    expect($result['result'])->toBeTrue();
});

it('throws when condition evaluates to false', function () {
    $config = ['expression' => "previous.status_code == 200"];
    $context = [
        'previous_outputs' => [
            'fetch_data' => ['status_code' => 500],
        ],
    ];

    expect(fn () => $this->executor->execute($config, $context))
        ->toThrow(RuntimeException::class, 'evaluated to false');
});

it('throws when expression is empty', function () {
    $config = ['expression' => ''];

    expect(fn () => $this->executor->execute($config, []))
        ->toThrow(RuntimeException::class, 'expression is required');
});

it('evaluates "true" literal as truthy', function () {
    $config = ['expression' => 'true'];

    $result = $this->executor->execute($config, []);
    expect($result['result'])->toBeTrue();
});

it('evaluates numeric comparison', function () {
    $config = ['expression' => 'previous.count >= 10'];
    $context = [
        'previous_outputs' => [
            'step_1' => ['count' => 15],
        ],
    ];

    $result = $this->executor->execute($config, $context);
    expect($result['result'])->toBeTrue();
});
