<?php

namespace App\Services;

use App\Contracts\AIServiceInterface;
use App\Services\Providers\GroqProvider;
use App\Services\Providers\MockAIProvider;
use InvalidArgumentException;

class AIServiceFactory
{
    public static function make(?string $provider = null): AIServiceInterface
    {
        $provider = $provider ?? config('ai.provider', 'mock');

        return match ($provider) {
            'groq', 'openai' => new GroqProvider(),
            'mock' => new MockAIProvider(),
            default => throw new InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }
}
