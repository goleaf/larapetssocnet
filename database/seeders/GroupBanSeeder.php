<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupBanSeeder extends Seeder
{
    public function run(): void
    {
        $groups = DB::table('groups')->get(['id', 'owner_user_id'])->all();
        $userIds = DB::table('users')->pluck('id')->all();

        if ($groups === [] || count($userIds) < 3) {
            return;
        }

        $faker = fake();
        $faker->seed(20260225);

        $rows = [];
        $keys = [];

        foreach ($groups as $group) {
            $memberIds = DB::table('group_members')
                ->where('group_id', $group->id)
                ->pluck('user_id')
                ->all();

            $nonMembers = array_values(array_diff($userIds, $memberIds));

            if ($nonMembers === []) {
                continue;
            }

            $banCount = $faker->numberBetween(0, min(2, count($nonMembers)));

            for ($i = 0; $i < $banCount; $i++) {
                $bannedId = $nonMembers[$faker->numberBetween(0, count($nonMembers) - 1)];
                $key = $group->id.':'.$bannedId;

                if (isset($keys[$key])) {
                    continue;
                }

                $keys[$key] = true;
                $createdAt = Carbon::instance($faker->dateTimeBetween('-60 days', 'now'));

                $rows[] = [
                    'group_id' => $group->id,
                    'user_id' => $bannedId,
                    'banned_by' => (int) $group->owner_user_id,
                    'reason' => $faker->boolean(60) ? $faker->sentence() : null,
                    'created_at' => $createdAt,
                ];
            }
        }

        if ($rows !== []) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('group_bans')->insertOrIgnore($chunk);
            }
        }
    }
}
