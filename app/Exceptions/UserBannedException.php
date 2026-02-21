<?php

namespace App\Exceptions;

use RuntimeException;

class UserBannedException extends RuntimeException
{
    public int $statusCode = 404;

    public function __construct(string $message = 'This user is not available.')
    {
        parent::__construct($message);
    }
}
