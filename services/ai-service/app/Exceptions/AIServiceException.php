<?php

namespace App\Exceptions;

use Exception;

class AIServiceException extends Exception
{
    public static function malformedResponse(string $detail = ''): self
    {
        return new self("AI returned a malformed response. {$detail}");
    }

    public static function providerError(string $detail = ''): self
    {
        return new self("AI provider error: {$detail}");
    }

    public static function tokenLimitExceeded(): self
    {
        return new self('Input exceeds maximum token limit');
    }

    public static function validationFailed(string $detail): self
    {
        return new self("AI output failed validation: {$detail}");
    }
}
