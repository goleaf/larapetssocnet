<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Support\Seeding\SeedProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PetSeeder extends Seeder
{
    /**
     * Seed pets for each user.
     */
    public function run(): void
    {
        $profile = SeedProfile::fromConfig();

        if ($profile === null) {
            $this->runLegacy();

            return;
        }

        $this->runProfile($profile);
    }

    private function runProfile(SeedProfile $profile): void
    {
        $users = User::query()->orderBy('id')->get(['id', 'name']);

        if ($users->isEmpty()) {
            return;
        }

        $targetPetCount = max(0, $profile->pets());

        if ($targetPetCount === 0) {
            return;
        }

        $userCount = $users->count();
        $basePerUser = intdiv($targetPetCount, $userCount);
        $remainder = $targetPetCount % $userCount;

        $species = ['dog', 'cat', 'rabbit', 'bird', 'hamster'];
        $breeds = ['Mixed', 'Maine Coon', 'Beagle', 'Budgie', 'Poodle', 'Parakeet'];

        $petIndex = 0;

        foreach ($users as $index => $user) {
            $petCount = $basePerUser + (($index < $remainder) ? 1 : 0);

            for ($offset = 0; $offset < $petCount; $offset++) {
                $petSpecies = $species[($petIndex + $offset) % count($species)];
                $petBreed = $breeds[($petIndex + $offset) % count($breeds)];
                $petName = trim((string) Str::of((string) $user->getAttribute('name'))->append("'s ")->append((string) $petSpecies));

                Pet::query()->updateOrCreate(
                    [
                        'user_id' => $user->getKey(),
                        'name' => $petName,
                    ],
                    [
                        'species' => $petSpecies,
                        'breed' => $petBreed,
                        'followers_count' => 0,
                        'posts_count' => 0,
                        'avatar_path' => $profile->mediaEnabled() && $petIndex < $profile->petsWithMedia()
                            ? sprintf('seed-media/pets/%s-pet-%s.jpg', $profile->value, str_pad((string) $petIndex, 3, '0', STR_PAD_LEFT))
                            : null,
                    ]
                );

                $petIndex++;
            }
        }

        DB::statement('UPDATE users SET pets_count = (SELECT COUNT(*) FROM pets WHERE pets.user_id = users.id)');
    }

    private function runLegacy(): void
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
