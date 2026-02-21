<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CounterCacheService
{
    public function rebuildAll(): void
    {
        DB::statement('UPDATE users SET followers_count = (SELECT COUNT(*) FROM user_follows WHERE user_follows.following_id = users.id)');
        DB::statement('UPDATE users SET following_count = (SELECT COUNT(*) FROM user_follows WHERE user_follows.follower_id = users.id)');
        DB::statement('UPDATE users SET pets_count = (SELECT COUNT(*) FROM pets WHERE pets.user_id = users.id)');
        DB::statement('UPDATE users SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id)');

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
