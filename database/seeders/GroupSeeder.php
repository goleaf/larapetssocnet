<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GroupSeeder extends Seeder
{
    private const TARGET_GROUP_COUNT = 24;

    /**
     * Seed groups and memberships.
     */
    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();
        $postIds = DB::table('posts')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        $faker = fake();
        $groupRows = [];
        $groupMeta = [];

        for ($i = 0; $i < self::TARGET_GROUP_COUNT; $i++) {
            $ownerId = $userIds[array_rand($userIds)];
            $name = Str::title($faker->unique()->words(random_int(2, 4), true));
            $createdAt = Carbon::instance($faker->dateTimeBetween('-70 days', 'now'));

            $groupId = DB::table('groups')->insertGetId([
                'owner_user_id' => $ownerId,
                'owner_id' => $ownerId,
                'name' => $name,
                'slug' => Str::slug($name).'-'.random_int(1000, 9999),
                'description' => random_int(1, 100) <= 80 ? $faker->sentence() : null,
                'privacy' => random_int(1, 100) <= 70 ? 'public' : 'private',
                'cover_image_path' => null,
                'members_count' => 0,
                'posts_count' => 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $groupRows[] = $groupId;
            $groupMeta[$groupId] = [
                'owner_id' => $ownerId,
                'created_at' => $createdAt,
            ];
        }

        $groupMembers = [];
        $memberMap = [];

        foreach ($groupRows as $groupId) {
            $ownerId = $groupMeta[$groupId]['owner_id'];
            $joinedAt = $groupMeta[$groupId]['created_at'];
            $maxMembers = min(24, count($userIds));
            $minMembers = min(8, $maxMembers);
            $targetMemberCount = random_int($minMembers, $maxMembers);
            $memberIds = array_values(array_unique(array_merge(
                [$ownerId],
                $this->pickRandomUnique($userIds, $targetMemberCount)
            )));

            $memberMap[$groupId] = $memberIds;

            foreach ($memberIds as $memberId) {
                $isOwner = $memberId === $ownerId;
                $status = $isOwner ? 'active' : (random_int(1, 100) <= 90 ? 'active' : 'pending');
                $role = $isOwner
                    ? 'owner'
                    : (random_int(1, 100) <= 12 ? 'admin' : 'member');

                $groupMembers[] = [
                    'group_id' => $groupId,
                    'user_id' => $memberId,
                    'role' => $role,
                    'status' => $status,
                    'joined_at' => $joinedAt,
                    'created_at' => $joinedAt,
                    'updated_at' => $joinedAt,
                ];
            }
        }

        foreach (array_chunk($groupMembers, 500) as $chunk) {
            DB::table('group_members')->insertOrIgnore($chunk);
        }

        $groupPosts = [];

        if ($postIds !== []) {
            foreach ($groupRows as $groupId) {
                $maxPosts = min(16, count($postIds));
                $minPosts = min(6, $maxPosts);
                $targetPosts = random_int($minPosts, $maxPosts);
                $memberIds = $memberMap[$groupId] ?? [];

                foreach ($this->pickRandomUnique($postIds, $targetPosts) as $postId) {
                    $createdAt = Carbon::instance($faker->dateTimeBetween('-40 days', 'now'));

                    $groupPosts[] = [
                        'group_id' => $groupId,
                        'post_id' => $postId,
                        'added_by_user_id' => $memberIds !== [] ? $memberIds[array_rand($memberIds)] : null,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }
        }

        if ($groupPosts !== []) {
            foreach (array_chunk($groupPosts, 500) as $chunk) {
                DB::table('group_posts')->insertOrIgnore($chunk);
            }
        }

        DB::statement("UPDATE groups SET members_count = (SELECT COUNT(*) FROM group_members WHERE group_members.group_id = groups.id AND group_members.status = 'active')");
        DB::statement('UPDATE groups SET posts_count = (SELECT COUNT(*) FROM group_posts WHERE group_posts.group_id = groups.id)');
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
