<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Deactivated = 'deactivated';
    case Suspended = 'suspended';
    case Banned = 'banned';
    case PendingDeletion = 'pending_deletion';
}
