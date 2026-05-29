<?php

namespace App\Enums\Pets;

enum PetWeightUnit: string
{
    case Kilograms = 'kg';
    case Pounds = 'lbs';

    public function label(): string
    {
        return match ($this) {
            self::Kilograms => 'kg',
            self::Pounds => 'lbs',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $unit): string => $unit->value, self::cases());
    }
}
