<?php

namespace App\Exceptions;

use RuntimeException;

class UsernameReservedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This username is reserved and cannot be used.');
    }
}

