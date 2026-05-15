<?php

namespace App\Enums;

enum GroupMemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Moderator = 'moderator';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Moderator => 'Moderator',
            self::Member => 'Member',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full group control, including ownership and destructive settings.',
            self::Admin => 'Can manage group settings, members, and moderation actions.',
            self::Moderator => 'Can moderate group content and member safety actions.',
            self::Member => 'Can participate according to group privacy and posting rules.',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Moderator => 2,
            self::Member => 1,
        };
    }

    public function isManager(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    public function canModerate(): bool
    {
        return $this->rank() >= self::Moderator->rank();
    }

    public function canManage(self $target): bool
    {
        return $this->rank() > $target->rank();
    }

    public function canAssign(self $target): bool
    {
        return $target !== self::Owner && $this->canManage($target);
    }

    public function nextPromotion(): ?self
    {
        return match ($this) {
            self::Member => self::Moderator,
            self::Moderator => self::Admin,
            self::Admin,
            self::Owner => null,
        };
    }

    public function nextDemotion(): ?self
    {
        return match ($this) {
            self::Admin => self::Moderator,
            self::Moderator => self::Member,
            self::Member,
            self::Owner => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function managerValues(): array
    {
        return [
            self::Owner->value,
            self::Admin->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function moderatorValues(): array
    {
        return [
            self::Owner->value,
            self::Admin->value,
            self::Moderator->value,
        ];
    }

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

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $role): array {
                $options[$role->value] = $role->label();

                return $options;
            },
            [],
        );
    }
}
