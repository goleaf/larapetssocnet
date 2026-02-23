<?php

namespace App\Exceptions;

use RuntimeException;

class UsernameChangeCooldownException extends RuntimeException
{
    public function __construct(public readonly int $daysRemaining)
    {
        parent::__construct("You can change your username again in {$daysRemaining} day(s).");
    }
}
