<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class CannotBlockAdminException extends Exception
{
    public function __construct(string $message = 'You cannot block an admin or moderator.')
    {
        parent::__construct($message);
    }
}
