<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class UsernameNotAvailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This username is not available.');
    }
}
