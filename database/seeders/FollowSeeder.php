<?php

namespace Database\Seeders;

use App\Models\Identity\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FollowSeeder extends Seeder
{
    /**
     * Seed follow relationships.
     */
    public function run(): void
    {
        $userIds = User::query()->orderBy('id')->pluck('id')->all();

        if (count($userIds) < 2) {
            return;
        }

        $faker = fake();
        $faker->seed(20260223);
        mt_srand(20260223);

        $rows = [];

        foreach ($userIds as $followerId) {
            $candidateIds = array_values(array_filter(
                $userIds,
                static fn (int $id): bool => $id !== $followerId
            ));

            shuffle($candidateIds);

            $followCount = $faker->numberBetween(5, min(10, count($candidateIds)));

            foreach (array_slice($candidateIds, 0, $followCount) as $followingId) {
                $createdAt = Carbon::instance($faker->dateTimeBetween('-60 days', 'now'));

                $rows[] = [
                    'follower_id' => $followerId,
                    'following_id' => $followingId,
                    'status' => 'accepted',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('follows')->insertOrIgnore($chunk);
        }

        DB::statement("UPDATE users SET following_count = (SELECT COUNT(*) FROM follows WHERE follows.follower_id = users.id AND follows.status = 'accepted')");
        DB::statement("UPDATE users SET followers_count = (SELECT COUNT(*) FROM follows WHERE follows.following_id = users.id AND follows.status = 'accepted')");
        DB::statement("UPDATE users SET follow_requests_count = (SELECT COUNT(*) FROM follows WHERE follows.following_id = users.id AND follows.status = 'pending')");
    }
}
