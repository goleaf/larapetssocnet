<?php

namespace App\Enums;

enum ProfileVisibility: string
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

    public function description(): string
    {
        return match ($this) {
            self::Public => 'Anyone can view the profile, subject to block and safety rules.',
            self::FollowersOnly => 'Followers can view profile details and profile-only content.',
            self::Private => 'Only the profile owner and privileged moderators can view private details.',
        };
    }

    public function level(): int
    {
        return match ($this) {
            self::Public => 0,
            self::FollowersOnly => 1,
            self::Private => 2,
        };
    }

    public function isMoreRestrictiveThan(self $visibility): bool
    {
        return $this->level() > $visibility->level();
    }

    public function allowsGuestProfile(): bool
    {
        return $this === self::Public;
    }

    public function marksAccountPrivate(): bool
    {
        return $this !== self::Public;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $visibility): string => $visibility->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $visibility): array {
                $options[$visibility->value] = $visibility->label();

                return $options;
            },
            [],
        );
    }
}
