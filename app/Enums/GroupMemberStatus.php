<?php

namespace App\Enums;

enum GroupMemberStatus: string
{
    case Active = 'active';
    case Accepted = 'accepted';
    case Pending = 'pending';
    case Rejected = 'rejected';
    case Removed = 'removed';
    case Banned = 'banned';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return [
            self::Active->value,
            self::Accepted->value,
        ];
    }
}
