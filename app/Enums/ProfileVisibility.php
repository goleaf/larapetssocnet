<?php

namespace App\Enums;

enum ProfileVisibility
{
    case Public = 'public';

    case FollowersOnly = 'followers_only';

    case Private = 'private';

    public static function fromValue(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        return match ($value) {
            self::Public->value => self::Public,
            self::FollowersOnly->value => self::FollowersOnly,
            self::Private->value => self::Private,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::FollowersOnly => 'Followers only',
            self::Private => 'Only me',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Public => '🌍',
            self::FollowersOnly => '👥',
            self::Private => '🔒',
        };
    }
}
