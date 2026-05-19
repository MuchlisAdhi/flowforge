<?php

namespace App\Exceptions;

use Exception;

class DagCycleException extends Exception
{
    public function __construct(string $message = 'Cycle detected in DAG', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
