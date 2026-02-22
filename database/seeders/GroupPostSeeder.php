<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GroupPostSeeder extends Seeder
{
    public function run(): void
    {
        // Group posts are seeded as part of GroupSeeder.
        // This seeder adds extra group post associations when run standalone.
        $groupIds = DB::table('groups')->pluck('id')->all();
        $postIds = DB::table('posts')->pluck('id')->all();

        if ($groupIds === [] || $postIds === []) {
            return;
        }

        $faker = fake();
        $faker->seed(20260228);

        $existing = DB::table('group_posts')
            ->get(['group_id', 'post_id'])
            ->map(fn ($row) => "{$row->group_id}:{$row->post_id}")
            ->flip()
            ->all();

        $rows = [];
        $keys = [];

        foreach ($groupIds as $groupId) {
            $memberIds = DB::table('group_members')
                ->where('group_id', $groupId)
                ->where('status', 'active')
                ->pluck('user_id')
                ->all();

            $count = $faker->numberBetween(0, 4);
            shuffle($postIds);

            $added = 0;

            foreach ($postIds as $postId) {
                if ($added >= $count) {
                    break;
                }

                $key = "{$groupId}:{$postId}";

                if (isset($existing[$key]) || isset($keys[$key])) {
                    continue;
                }

                $keys[$key] = true;
                $added++;
                $createdAt = Carbon::instance($faker->dateTimeBetween('-30 days', 'now'));

                $rows[] = [
                    'group_id' => $groupId,
                    'post_id' => $postId,
                    'added_by_user_id' => $memberIds !== [] ? $memberIds[array_rand($memberIds)] : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        if ($rows !== []) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('group_posts')->insertOrIgnore($chunk);
            }

            DB::statement('UPDATE groups SET posts_count = (SELECT COUNT(*) FROM group_posts WHERE group_posts.group_id = groups.id)');
        }
    }
}
