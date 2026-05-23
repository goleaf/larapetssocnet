<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Deactivated = 'deactivated';
    case Suspended = 'suspended';
    case PendingDeletion = 'pending-deletion';
}
