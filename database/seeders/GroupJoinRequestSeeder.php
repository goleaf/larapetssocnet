<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupJoinRequestSeeder extends Seeder
{
    public function run(): void
    {
        $privateGroups = DB::table('groups')
            ->where('privacy', 'private')
            ->get(['id'])
            ->all();

        if ($privateGroups === []) {
            return;
        }

        $userIds = DB::table('users')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        $faker = fake();
        $faker->seed(20260226);

        $rows = [];
        $keys = [];

        foreach ($privateGroups as $group) {
            $existingMemberIds = DB::table('group_members')
                ->where('group_id', $group->id)
                ->pluck('user_id')
                ->all();

            $candidates = array_values(array_diff($userIds, $existingMemberIds));

            if ($candidates === []) {
                continue;
            }

            $requestCount = $faker->numberBetween(1, min(4, count($candidates)));
            shuffle($candidates);

            foreach (array_slice($candidates, 0, $requestCount) as $requesterId) {
                $key = $group->id.':'.$requesterId;

                if (isset($keys[$key])) {
                    continue;
                }

                $keys[$key] = true;
                $createdAt = Carbon::instance($faker->dateTimeBetween('-30 days', 'now'));

                $rows[] = [
                    'group_id' => $group->id,
                    'user_id' => $requesterId,
                    'status' => $faker->randomElement(['pending', 'pending', 'approved', 'rejected']),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'message' => $faker->boolean(40) ? $faker->sentence() : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        if ($rows !== []) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('group_join_requests')->insertOrIgnore($chunk);
            }
        }
    }
}
