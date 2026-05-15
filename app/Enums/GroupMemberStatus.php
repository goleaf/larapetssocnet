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

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Accepted => 'Accepted',
            self::Pending => 'Pending',
            self::Rejected => 'Rejected',
            self::Removed => 'Removed',
            self::Banned => 'Banned',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Active => 'The user is an active member of the group.',
            self::Accepted => 'The user has been accepted and should be treated as active.',
            self::Pending => 'The user is waiting for a join request decision.',
            self::Rejected => 'The latest join request was rejected.',
            self::Removed => 'The user was removed from group membership.',
            self::Banned => 'The user is blocked from joining or viewing restricted group areas.',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Active, self::Accepted], true);
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isBanned(): bool
    {
        return $this === self::Banned;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Removed, self::Banned], true);
    }

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
        return array_map(
            static fn (self $status): string => $status->value,
            self::activeCases(),
        );
    }

    /**
     * @return list<self>
     */
    public static function activeCases(): array
    {
        return [
            self::Active,
            self::Accepted,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $status): array {
                $options[$status->value] = $status->label();

                return $options;
            },
            [],
        );
    }
}
