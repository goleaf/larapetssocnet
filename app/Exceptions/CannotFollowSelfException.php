<?php

namespace App\Exceptions;

use RuntimeException;

class CannotFollowSelfException extends RuntimeException
{
    public int $statusCode = 422;

    public function __construct(string $message = 'You cannot follow yourself.')
    {
        parent::__construct($message);
    }
}
