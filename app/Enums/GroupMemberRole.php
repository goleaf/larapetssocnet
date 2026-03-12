<?php

namespace App\Enums;

enum GroupMemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Member = 'member';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
