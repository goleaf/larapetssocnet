<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Sent = 'sent';

    case Delivered = 'delivered';

    case Read = 'read';
}
