<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class UsernameChangeCooldownException extends RuntimeException
{
    public function __construct(public readonly int $daysRemaining)
    {
        $cooldownDays = (int) config('usernames.cooldown_days', 30);

        parent::__construct("You can only change your username once every {$cooldownDays} days. Your next change is available in {$daysRemaining} days.");
    }
}
