<?php

namespace App\Services\StepExecutors;

use InvalidArgumentException;

class StepExecutorFactory
{
    public function make(string $type): StepExecutorInterface
    {
        return match ($type) {
            'http' => new HttpStepExecutor(),
            'script' => new ScriptStepExecutor(),
            'delay' => new DelayStepExecutor(),
            'condition' => new ConditionStepExecutor(),
            default => throw new InvalidArgumentException("Unknown step type: {$type}"),
        };
    }
}
