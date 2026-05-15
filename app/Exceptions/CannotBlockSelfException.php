<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CannotBlockSelfException extends Exception
{
    public function __construct(string $message = 'You cannot block yourself.')
    {
        parent::__construct($message);
    }
}
