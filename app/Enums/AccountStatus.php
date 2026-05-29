<?php

declare(strict_types=1);

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Deactivated = 'deactivated';
    case Suspended = 'suspended';
    case PendingDeletion = 'pending-deletion';
}
