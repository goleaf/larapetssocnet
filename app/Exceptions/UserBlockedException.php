<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class UserBlockedException extends RuntimeException
{
    public int $statusCode = 403;

    public function __construct(string $message = 'Unable to perform this action.')
    {
        parent::__construct($message);
    }
}
