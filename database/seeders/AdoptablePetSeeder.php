<?php

namespace Database\Seeders;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class AdoptablePetSeeder extends Seeder
{
    public function run(): void
    {
        $owners = User::query()->orderBy('id')->limit(4)->get();

        if ($owners->isEmpty()) {
            return;
        }

        $profiles = [
            [
                'name' => 'Luna',
                'species' => 'dog',
                'breed' => 'Mixed',
                'sex' => 'female',
                'bio' => 'Friendly and calm. Loves short walks and cuddle time.',
                'adoption_fee' => 120,
                'adoption_notes' => 'Spayed, vaccinated, and crate trained.',
            ],
            [
                'name' => 'Milo',
                'species' => 'cat',
                'breed' => 'Tabby',
                'sex' => 'male',
                'bio' => 'Indoor cat with a playful personality and good social skills.',
                'adoption_fee' => 60,
                'adoption_notes' => 'Great with kids and used to apartment living.',
            ],
            [
                'name' => 'Poppy',
                'species' => 'rabbit',
                'breed' => 'Mini Lop',
                'sex' => 'female',
                'bio' => 'Gentle rabbit that enjoys quiet homes and fresh greens.',
                'adoption_fee' => 40,
                'adoption_notes' => 'Litter trained and easy to handle.',
            ],
            [
                'name' => 'Koda',
                'species' => 'dog',
                'breed' => 'Beagle',
                'sex' => 'male',
                'bio' => 'Smart and energetic companion, perfect for active owners.',
                'adoption_fee' => 140,
                'adoption_notes' => 'Knows basic commands and walks well on leash.',
            ],
            [
                'name' => 'Willow',
                'species' => 'cat',
                'breed' => 'Calico',
                'sex' => 'female',
                'bio' => 'Quiet and affectionate cat that bonds quickly with people.',
                'adoption_fee' => 0,
                'adoption_notes' => 'Fee waived for quick placement.',
            ],
            [
                'name' => 'Pebble',
                'species' => 'hamster',
                'breed' => 'Syrian',
                'sex' => 'unknown',
                'bio' => 'Low-maintenance pet, ideal for first-time adopters.',
                'adoption_fee' => 25,
                'adoption_notes' => 'Comes with starter care notes.',
            ],
        ];

        foreach ($profiles as $index => $profile) {
            $attributes = [
                'name' => $profile['name'],
                'species' => $profile['species'],
                'breed' => $profile['breed'],
                'sex' => $profile['sex'],
                'bio' => $profile['bio'],
                'is_public' => true,
                'followers_count' => 0,
                'posts_count' => 0,
                'avatar_path' => null,
            ];

            if (Schema::hasColumn('pets', 'is_adoptable')) {
                $attributes['is_adoptable'] = true;
            }

            if (Schema::hasColumn('pets', 'adoption_status')) {
                $attributes['adoption_status'] = 'available';
            }

            if (Schema::hasColumn('pets', 'adoption_fee')) {
                $attributes['adoption_fee'] = $profile['adoption_fee'];
            }

            if (Schema::hasColumn('pets', 'adoption_notes')) {
                $attributes['adoption_notes'] = $profile['adoption_notes'];
            }

            if (Schema::hasColumn('pets', 'adoption_contact')) {
                $attributes['adoption_contact'] = 'adoptions@larapets.test';
            }

            if (Schema::hasColumn('pets', 'adoption_listed_at')) {
                $attributes['adoption_listed_at'] = now()->subDays($index + 1);
            }

            Pet::factory()
                ->for($owners[$index % $owners->count()])
                ->create($attributes);
        }
    }
}
