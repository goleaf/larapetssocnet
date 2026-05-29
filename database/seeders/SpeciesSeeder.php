<?php

namespace Database\Seeders;

use App\Models\Pets\Species;
use Illuminate\Database\Seeder;

class SpeciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $order = 1;

        foreach (config('pets.species', []) as $slug => $species) {
            Species::query()->updateOrCreate([
                'slug' => $slug,
            ], [
                'name' => (string) ($species['label'] ?? str($slug)->headline()),
                'icon_identifier' => (string) ($species['icon'] ?? 'paw'),
                'color_identifier' => (string) ($species['color'] ?? $slug),
                'gradient_from' => (string) ($species['gradient_from'] ?? $this->gradientStop((string) ($species['gradient'] ?? ''), 0)),
                'gradient_to' => (string) ($species['gradient_to'] ?? $this->gradientStop((string) ($species['gradient'] ?? ''), -1)),
                'display_order' => $order++,
                'life_stage_config' => json_encode($species['life_stages'] ?? $this->defaultLifeStages(), JSON_THROW_ON_ERROR),
            ]);
        }
    }

    /**
     * @return list<array{name: string, starts_at_month: int, color: string}>
     */
    private function defaultLifeStages(): array
    {
        return [
            ['name' => 'Baby', 'starts_at_month' => 0, 'color' => 'bg-amber-100 text-amber-900'],
            ['name' => 'Young', 'starts_at_month' => 6, 'color' => 'bg-sky-100 text-sky-900'],
            ['name' => 'Adult', 'starts_at_month' => 24, 'color' => 'bg-emerald-100 text-emerald-900'],
            ['name' => 'Senior', 'starts_at_month' => 84, 'color' => 'bg-violet-100 text-violet-900'],
        ];
    }

    private function gradientStop(string $gradient, int $position): string
    {
        preg_match_all('/#[A-Fa-f0-9]{6}/', $gradient, $matches);
        $stops = $matches[0] ?? [];

        if ($stops === []) {
            return $position === 0 ? '#F6D2A8' : '#A4572B';
        }

        return $position === 0 ? $stops[0] : $stops[array_key_last($stops)];
    }
}
