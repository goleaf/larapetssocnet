<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupMemberSeeder extends Seeder
{
    public function run(): void
    {
        // Group membership is seeded as part of GroupSeeder.
        // This seeder adds extra members to existing groups when run standalone.
        $groupIds = DB::table('groups')->pluck('id')->all();
        $userIds = DB::table('users')->pluck('id')->all();

        if ($groupIds === [] || count($userIds) < 2) {
            return;
        }

        $faker = fake();
        $faker->seed(20260227);

        $rows = [];
        $keys = [];

        $existing = DB::table('group_members')
            ->get(['group_id', 'user_id'])
            ->map(fn ($row) => "{$row->group_id}:{$row->user_id}")
            ->flip()
            ->all();

        foreach ($groupIds as $groupId) {
            $newMemberCount = $faker->numberBetween(0, 3);
            shuffle($userIds);

            $added = 0;

            foreach ($userIds as $userId) {
                if ($added >= $newMemberCount) {
                    break;
                }

                $key = "{$groupId}:{$userId}";

                if (isset($existing[$key]) || isset($keys[$key])) {
                    continue;
                }

                $keys[$key] = true;
                $added++;
                $joinedAt = Carbon::instance($faker->dateTimeBetween('-30 days', 'now'));

                $rows[] = [
                    'group_id' => $groupId,
                    'user_id' => $userId,
                    'role' => 'member',
                    'status' => 'active',
                    'joined_at' => $joinedAt,
                    'created_at' => $joinedAt,
                    'updated_at' => $joinedAt,
                ];
            }
        }

        if ($rows !== []) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('group_members')->insertOrIgnore($chunk);
            }

            DB::statement("UPDATE groups SET members_count = (SELECT COUNT(*) FROM group_members WHERE group_members.group_id = groups.id AND group_members.status = 'active')");
        }
    }
}
