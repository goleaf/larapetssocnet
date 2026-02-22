<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Badge::PREDEFINED as $definition) {
            Badge::updateOrCreate(
                ['slug' => $definition['slug']],
                array_merge([
                    'name' => $definition['name'],
                    'icon' => $definition['icon'] ?? '🏷',
                    'color' => $definition['color'] ?? 'emerald',
                    'type' => $definition['type'] ?? 'auto',
                    'condition_type' => $definition['condition_type'] ?? null,
                    'condition_value' => $definition['condition_value'] ?? null,
                ])
            );
        }
    }
}
