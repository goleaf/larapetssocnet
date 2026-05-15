<?php

namespace Database\Seeders;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PetSeeder extends Seeder
{
    /**
     * Seed pets for each user.
     */
    public function run(): void
    {
        $users = User::query()->orderBy('id')->get(['id']);

        if ($users->isEmpty()) {
            return;
        }

        $faker = fake();
        $faker->seed(20260222);

        foreach ($users as $user) {
            $petCount = $faker->numberBetween(1, 3);

            Pet::factory()
                ->count($petCount)
                ->for($user)
                ->create([
                    'avatar_path' => null,
                    'followers_count' => 0,
                    'posts_count' => 0,
                ]);
        }

        DB::statement('UPDATE users SET pets_count = (SELECT COUNT(*) FROM pets WHERE pets.user_id = users.id)');
    }
}
