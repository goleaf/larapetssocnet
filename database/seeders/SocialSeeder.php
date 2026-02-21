<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SocialSeeder extends Seeder
{
    /**
     * Seed social graph data.
     */
    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();
        $petIds = DB::table('pets')->pluck('id')->all();

        if (count($userIds) < 2) {
            return;
        }

        $faker = fake();

        $follows = [];
        $followKeys = [];

        foreach ($userIds as $followerId) {
            $maxFollows = min(12, count($userIds) - 1);

            if ($maxFollows < 1) {
                continue;
            }

            $target = random_int(3, $maxFollows);
            $createdForUser = 0;
            $attempts = 0;

            while ($createdForUser < $target && $attempts < $target * 10) {
                $attempts++;
                $followedId = $userIds[array_rand($userIds)];

                if ($followedId === $followerId) {
                    continue;
                }

                $key = $followerId.':'.$followedId;

                if (isset($followKeys[$key])) {
                    continue;
                }

                $followKeys[$key] = true;
                $createdAt = Carbon::instance($faker->dateTimeBetween('-90 days', 'now'));

                $follows[] = [
                    'follower_id' => $followerId,
                    'following_id' => $followedId,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];

                $createdForUser++;
            }
        }

        if ($follows !== []) {
            foreach (array_chunk($follows, 500) as $chunk) {
                DB::table('user_follows')->insertOrIgnore($chunk);
            }
        }

        $petFollows = [];

        if ($petIds !== []) {
            foreach ($userIds as $userId) {
                $maxPetFollows = min(8, count($petIds));
                $target = $maxPetFollows > 1 ? random_int(2, $maxPetFollows) : 1;

                foreach ($this->pickRandomUnique($petIds, $target) as $petId) {
                    $createdAt = Carbon::instance($faker->dateTimeBetween('-90 days', 'now'));

                    $petFollows[] = [
                        'user_id' => $userId,
                        'pet_id' => $petId,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }
        }

        if ($petFollows !== []) {
            foreach (array_chunk($petFollows, 500) as $chunk) {
                DB::table('pet_followers')->insertOrIgnore($chunk);
            }
        }

        $blocks = [];
        $blockTargets = max(30, intdiv(count($userIds), 2));
        $blockKeys = [];
        $attempts = 0;

        while (count($blocks) < $blockTargets && $attempts < $blockTargets * 20) {
            $attempts++;
            $blockerId = $userIds[array_rand($userIds)];
            $blockedId = $userIds[array_rand($userIds)];

            if ($blockerId === $blockedId) {
                continue;
            }

            $key = $blockerId.':'.$blockedId;

            if (isset($blockKeys[$key])) {
                continue;
            }

            $blockKeys[$key] = true;
            $createdAt = Carbon::instance($faker->dateTimeBetween('-60 days', 'now'));

            $blocks[] = [
                'blocker_id' => $blockerId,
                'blocked_id' => $blockedId,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        if ($blocks !== []) {
            DB::table('user_blocks')->insertOrIgnore($blocks);
        }

        DB::statement('UPDATE users SET following_count = (SELECT COUNT(*) FROM user_follows WHERE user_follows.follower_id = users.id)');
        DB::statement('UPDATE users SET followers_count = (SELECT COUNT(*) FROM user_follows WHERE user_follows.following_id = users.id)');
        DB::statement('UPDATE pets SET followers_count = (SELECT COUNT(*) FROM pet_followers WHERE pet_followers.pet_id = pets.id)');
    }

    /**
     * @param  list<int>  $source
     * @return list<int>
     */
    private function pickRandomUnique(array $source, int $count): array
    {
        if ($source === []) {
            return [];
        }

        shuffle($source);

        return array_slice($source, 0, min($count, count($source)));
    }
}
