<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthSeeder extends Seeder
{
    private const TARGET_HEALTH_LOG_COUNT = 280;

    /**
     * Seed pet health logs and finalize counter caches.
     */
    public function run(): void
    {
        $pets = DB::table('pets')->get(['id', 'user_id']);
        $userIds = DB::table('users')->pluck('id')->all();

        if ($pets->isEmpty() || $userIds === []) {
            return;
        }

        $petRows = $pets->map(static fn ($pet): array => ['id' => (int) $pet->id, 'user_id' => (int) $pet->user_id])->all();
        $faker = fake();
        $rows = [];

        for ($i = 0; $i < self::TARGET_HEALTH_LOG_COUNT; $i++) {
            $pet = $petRows[array_rand($petRows)];
            $loggedAt = Carbon::instance($faker->dateTimeBetween('-200 days', 'now'));

            $rows[] = [
                'pet_id' => $pet['id'],
                'logged_by_user_id' => random_int(1, 100) <= 70 ? $pet['user_id'] : $userIds[array_rand($userIds)],
                'log_type' => $faker->randomElement(['checkup', 'vaccine', 'medication', 'weight']),
                'title' => random_int(1, 100) <= 75 ? $faker->sentence(random_int(2, 5)) : null,
                'notes' => random_int(1, 100) <= 70 ? $faker->sentence() : null,
                'weight_kg' => random_int(1, 100) <= 65 ? $faker->randomFloat(2, 1, 60) : null,
                'temperature_c' => random_int(1, 100) <= 25 ? $faker->randomFloat(1, 36.0, 40.0) : null,
                'logged_at' => $loggedAt,
                'created_at' => $loggedAt,
                'updated_at' => $loggedAt,
            ];
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('pet_health_logs')->insert($chunk);
        }

        $this->refreshCounterCaches();
    }

    private function refreshCounterCaches(): void
    {
        DB::statement('UPDATE users SET followers_count = (SELECT COUNT(*) FROM user_follows WHERE user_follows.following_id = users.id)');
        DB::statement('UPDATE users SET following_count = (SELECT COUNT(*) FROM user_follows WHERE user_follows.follower_id = users.id)');
        DB::statement('UPDATE users SET pets_count = (SELECT COUNT(*) FROM pets WHERE pets.user_id = users.id)');
        DB::statement('UPDATE users SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id)');
        if (Schema::hasColumn('users', 'scheduled_posts_count')) {
            DB::statement("UPDATE users SET scheduled_posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id AND posts.status = 'scheduled' AND posts.deleted_at IS NULL)");
        }

        DB::statement('UPDATE pets SET followers_count = (SELECT COUNT(*) FROM pet_followers WHERE pet_followers.pet_id = pets.id)');
        DB::statement('UPDATE pets SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.pet_id = pets.id)');

        DB::statement('UPDATE posts SET comments_count = (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)');
        DB::statement("UPDATE posts SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id)");
        DB::statement('UPDATE posts SET likes_count = reactions_count');
        DB::statement("UPDATE comments SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id)");

        DB::statement("UPDATE groups SET members_count = (SELECT COUNT(*) FROM group_members WHERE group_members.group_id = groups.id AND group_members.status = 'active')");
        DB::statement('UPDATE groups SET posts_count = (SELECT COUNT(*) FROM group_posts WHERE group_posts.group_id = groups.id)');

        DB::statement("UPDATE events SET attendees_count = (SELECT COUNT(*) FROM event_attendees WHERE event_attendees.event_id = events.id AND event_attendees.status = 'going')");

        DB::statement('UPDATE hashtags SET posts_count = (SELECT COUNT(*) FROM post_hashtag WHERE post_hashtag.hashtag_id = hashtags.id)');
    }
}
