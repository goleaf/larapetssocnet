<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PetSeeder extends Seeder
{
    private const TARGET_PET_COUNT = 160;

    /**
     * Seed pets for the MVP wave.
     */
    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        $faker = fake();
        $rows = [];

        for ($i = 0; $i < self::TARGET_PET_COUNT; $i++) {
            $createdAt = Carbon::instance($faker->dateTimeBetween('-120 days', 'now'));

            $rows[] = [
                'user_id' => $userIds[array_rand($userIds)],
                'name' => $faker->firstName(),
                'species' => $faker->randomElement(['dog', 'cat', 'bird', 'rabbit', 'hamster']),
                'breed' => random_int(1, 100) <= 80 ? ucfirst($faker->word()) : null,
                'sex' => $faker->randomElement(['male', 'female']),
                'birth_date' => random_int(1, 100) <= 85
                    ? Carbon::instance($faker->dateTimeBetween('-15 years', '-4 months'))->toDateString()
                    : null,
                'bio' => random_int(1, 100) <= 70 ? $faker->sentence() : null,
                'avatar_path' => null,
                'is_public' => random_int(1, 100) <= 92,
                'followers_count' => 0,
                'posts_count' => 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('pets')->insert($chunk);
        }

        DB::statement('UPDATE users SET pets_count = (SELECT COUNT(*) FROM pets WHERE pets.user_id = users.id)');
    }
}
