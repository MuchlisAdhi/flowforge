<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Immutable DTO representing a validated workflow creation/update request.
 * Separates request validation (FormRequest) from business logic data shape.
 */
final readonly class WorkflowData
{
    /**
     * @param array<int, array<string, mixed>> $steps
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public int $timeoutSeconds,
        public array $steps,
        public ?string $changeNote = null,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            timeoutSeconds: $validated['timeout_seconds'] ?? 300,
            steps: $validated['steps'],
            changeNote: $validated['change_note'] ?? null,
        );
    }
}
