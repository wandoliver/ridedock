<?php

namespace App\Domain\Operations\Exceptions;

use RuntimeException;

final class WorkDeferred extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('Shared service capacity is temporarily unavailable.');
    }
}
