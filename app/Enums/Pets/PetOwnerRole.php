<?php

namespace App\Enums\Pets;

enum PetOwnerRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Poster = 'poster';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Poster => 'Poster',
            self::Viewer => 'Viewer',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 4,
            self::Admin => 3,
            self::Poster => 2,
            self::Viewer => 1,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
