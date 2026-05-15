<?php

namespace Database\Seeders;

use App\Models\Identity\User;
use App\Services\CounterCacheService;
use App\Services\FollowService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

class SocialSeeder extends Seeder
{
    /**
     * Seed social graph data.
     */
    public function run(): void
    {
        $users = User::query()->where('is_banned', false)->get();
        $userIds = $users->pluck('id')->all();
        $petIds = DB::table('pets')->pluck('id')->all();

        if (count($userIds) < 2) {
            return;
        }

        $faker = fake();
        $followService = app(FollowService::class);

        $users->each(function (User $user) use ($users, $followService): void {
            $targets = $users
                ->where('id', '!=', $user->id)
                ->random(random_int(8, min(20, max($users->count() - 1, 1))));

            foreach ($targets as $target) {
                try {
                    $followService->follow($user, $target);
                } catch (Throwable) {
                    // Skip invalid pairs (self, blocked, duplicates, banned).
                }
            }
        });

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

        app(CounterCacheService::class)->rebuildFollowCounts();
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
