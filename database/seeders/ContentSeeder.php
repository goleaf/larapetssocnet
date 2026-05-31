<?php

namespace Database\Seeders;

use App\Models\Content\Post;
use App\Support\Seeding\SeedProfile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    private const TARGET_POST_COUNT = 320;

    /**
     * Seed content data.
     */
    public function run(): void
    {
        $profile = SeedProfile::fromConfig();

        if ($profile === null) {
            $this->runLegacy();

            return;
        }

        if ($profile->contentPosts() <= 0) {
            return;
        }

        $this->runProfile($profile);
    }

    private function runProfile(SeedProfile $profile): void
    {
        $seedPostCount = $profile->contentPosts();
        $userIds = DB::table('users')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        $faker = fake();

        if ($profile->seedHashtags()) {
            $this->seedHashtags();
        }

        $postIds = [];

        for ($i = 0; $i < $seedPostCount; $i++) {
            $userId = $userIds[$i % count($userIds)];
            $createdAt = Carbon::instance($faker->dateTimeBetween('-90 days', 'now'));

            $postIds[] = DB::table('posts')->insertGetId([
                'user_id' => $userId,
                'pet_id' => null,
                'body' => $faker->paragraph($faker->numberBetween(1, 4)),
                'visibility' => Post::visibilityValues()[array_rand(Post::visibilityValues())],
                'status' => 'published',
                'comments_count' => 0,
                'reactions_count' => 0,
                'published_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $commentIds = [];
        $targetComments = intdiv($seedPostCount, 2);

        for ($i = 0; $i < $targetComments; $i++) {
            $postId = $postIds[$i % count($postIds)];
            $createdAt = Carbon::instance($faker->dateTimeBetween('-75 days', 'now'));

            $commentIds[] = DB::table('comments')->insertGetId([
                'post_id' => $postId,
                'user_id' => $userIds[$i % count($userIds)],
                'parent_id' => null,
                'body' => $faker->sentence($faker->numberBetween(5, 14)),
                'reactions_count' => 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $reactions = [];

        if ($profile->seedReactionRows()) {
            $reactionTypes = ['paw', 'love', 'haha', 'wow', 'sad', 'angry'];

            $maxPostReactors = min(8, count($userIds));

            foreach ($postIds as $postId) {
                $reactorCount = max(1, min($maxPostReactors, count($userIds)));

                foreach ($this->pickRandomUnique($userIds, $reactorCount) as $index => $userId) {
                    $createdAt = Carbon::instance($faker->dateTimeBetween('-60 days', 'now'));

                    $reactions[] = [
                        'user_id' => $userId,
                        'reactable_type' => 'App\\Models\\Post',
                        'reactable_id' => $postId,
                        'type' => $reactionTypes[$index % count($reactionTypes)],
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }

            foreach ($commentIds as $commentId) {
                $maxCommentReactors = min(4, count($userIds));
                foreach ($this->pickRandomUnique($userIds, $maxCommentReactors) as $userId) {
                    $createdAt = Carbon::instance($faker->dateTimeBetween('-45 days', 'now'));

                    $reactions[] = [
                        'user_id' => $userId,
                        'reactable_type' => 'App\\Models\\Comment',
                        'reactable_id' => $commentId,
                        'type' => 'paw',
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }

            if ($reactions !== []) {
                foreach (array_chunk($reactions, 500) as $chunk) {
                    DB::table('reactions')->insertOrIgnore($chunk);
                }
            }
        }

        DB::statement('UPDATE posts SET comments_count = (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)');
        DB::statement("UPDATE posts SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id)");
        foreach (['paw', 'love', 'haha', 'wow', 'sad', 'angry'] as $type) {
            $column = $type.'_count';

            if (Schema::hasColumn('posts', $column)) {
                DB::statement("UPDATE posts SET {$column} = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id AND reactions.type = '{$type}')");
            }
        }
        DB::statement("UPDATE comments SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id)");
        foreach (['paw', 'love'] as $type) {
            $column = $type.'_count';

            if (Schema::hasColumn('comments', $column)) {
                DB::statement("UPDATE comments SET {$column} = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id AND reactions.type = '{$type}')");
            }
        }
        DB::statement('UPDATE pets SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.pet_id = pets.id)');
        DB::statement('UPDATE users SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id)');
        if (Schema::hasColumn('users', 'scheduled_posts_count')) {
            DB::statement("UPDATE users SET scheduled_posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id AND posts.status = 'scheduled' AND posts.deleted_at IS NULL)");
        }
        if (Schema::hasColumn('users', 'post_reactions_received_count')) {
            DB::statement('UPDATE users SET post_reactions_received_count = (SELECT COALESCE(SUM(posts.reactions_count), 0) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }
        if (Schema::hasColumn('users', 'post_comments_received_count')) {
            DB::statement('UPDATE users SET post_comments_received_count = (SELECT COALESCE(SUM(posts.comments_count), 0) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }
        if (Schema::hasColumn('users', 'last_post_created_at')) {
            DB::statement('UPDATE users SET last_post_created_at = (SELECT MAX(posts.created_at) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }
        DB::statement('UPDATE hashtags SET posts_count = (SELECT COUNT(*) FROM post_hashtag WHERE post_hashtag.hashtag_id = hashtags.id)');
    }

    private function runLegacy(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        $faker = fake();

        $this->seedHashtags();

        $hashtags = DB::table('hashtags')->pluck('id')->all();
        $petsByUser = DB::table('pets')
            ->select(['id', 'user_id'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($pets) => $pets->pluck('id')->all())
            ->all();

        $postIds = [];

        for ($i = 0; $i < self::TARGET_POST_COUNT; $i++) {
            $userId = $userIds[array_rand($userIds)];
            $userPetIds = $petsByUser[$userId] ?? [];
            $petId = null;

            if ($userPetIds !== [] && random_int(1, 100) <= 45) {
                $petId = $userPetIds[array_rand($userPetIds)];
            }

            $createdAt = Carbon::instance($faker->dateTimeBetween('-90 days', 'now'));

            $postIds[] = DB::table('posts')->insertGetId([
                'user_id' => $userId,
                'pet_id' => $petId,
                'body' => $faker->paragraph($faker->numberBetween(1, 4)),
                'visibility' => $faker->randomElement(Post::visibilityValues()),
                'status' => 'published',
                'comments_count' => 0,
                'reactions_count' => 0,
                'published_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $postHashtags = [];

        if ($hashtags !== []) {
            foreach ($postIds as $postId) {
                $maxTags = min(4, count($hashtags));
                $minTags = min(1, $maxTags);
                $tagCount = random_int($minTags, $maxTags);

                foreach ($this->pickRandomUnique($hashtags, $tagCount) as $hashtagId) {
                    $createdAt = Carbon::instance($faker->dateTimeBetween('-90 days', 'now'));

                    $postHashtags[] = [
                        'post_id' => $postId,
                        'hashtag_id' => $hashtagId,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
            }
        }

        if ($postHashtags !== []) {
            foreach (array_chunk($postHashtags, 500) as $chunk) {
                DB::table('post_hashtag')->insertOrIgnore($chunk);
            }
        }

        $commentIds = [];

        foreach ($postIds as $postId) {
            $topLevelCount = random_int(0, 3);

            for ($i = 0; $i < $topLevelCount; $i++) {
                $createdAt = Carbon::instance($faker->dateTimeBetween('-75 days', 'now'));

                $commentId = DB::table('comments')->insertGetId([
                    'post_id' => $postId,
                    'user_id' => $userIds[array_rand($userIds)],
                    'parent_id' => null,
                    'body' => $faker->sentence($faker->numberBetween(5, 14)),
                    'reactions_count' => 0,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $commentIds[] = $commentId;

                if (random_int(1, 100) <= 25) {
                    $replyCreatedAt = Carbon::instance($faker->dateTimeBetween($createdAt, 'now'));

                    $replyId = DB::table('comments')->insertGetId([
                        'post_id' => $postId,
                        'user_id' => $userIds[array_rand($userIds)],
                        'parent_id' => $commentId,
                        'body' => $faker->sentence($faker->numberBetween(4, 10)),
                        'reactions_count' => 0,
                        'created_at' => $replyCreatedAt,
                        'updated_at' => $replyCreatedAt,
                    ]);

                    $commentIds[] = $replyId;
                }
            }
        }

        $reactions = [];
        $reactionTypes = ['paw', 'love', 'haha', 'wow', 'sad', 'angry'];

        $maxPostReactors = min(8, count($userIds));

        foreach ($postIds as $postId) {
            $reactorCount = $maxPostReactors > 1
                ? random_int(1, $maxPostReactors)
                : 1;

            foreach ($this->pickRandomUnique($userIds, $reactorCount) as $userId) {
                $createdAt = Carbon::instance($faker->dateTimeBetween('-60 days', 'now'));

                $reactions[] = [
                    'user_id' => $userId,
                    'reactable_type' => 'App\\Models\\Post',
                    'reactable_id' => $postId,
                    'type' => $reactionTypes[array_rand($reactionTypes)],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        $maxCommentReactors = min(4, count($userIds));

        foreach ($commentIds as $commentId) {
            if ($maxCommentReactors < 1) {
                continue;
            }

            $reactorCount = random_int(0, $maxCommentReactors);

            if ($reactorCount === 0) {
                continue;
            }

            foreach ($this->pickRandomUnique($userIds, $reactorCount) as $userId) {
                $createdAt = Carbon::instance($faker->dateTimeBetween('-45 days', 'now'));

                $reactions[] = [
                    'user_id' => $userId,
                    'reactable_type' => 'App\\Models\\Comment',
                    'reactable_id' => $commentId,
                    'type' => $reactionTypes[array_rand($reactionTypes)],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        if ($reactions !== []) {
            foreach (array_chunk($reactions, 500) as $chunk) {
                DB::table('reactions')->insertOrIgnore($chunk);
            }
        }

        $savedPosts = [];

        foreach ($userIds as $userId) {
            $maxSaved = min(12, count($postIds));

            if ($maxSaved < 1) {
                continue;
            }

            $minSaved = min(4, $maxSaved);
            $saveCount = random_int($minSaved, $maxSaved);

            foreach ($this->pickRandomUnique($postIds, $saveCount) as $postId) {
                $createdAt = Carbon::instance($faker->dateTimeBetween('-30 days', 'now'));

                $savedPosts[] = [
                    'user_id' => $userId,
                    'post_id' => $postId,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        if ($savedPosts !== []) {
            foreach (array_chunk($savedPosts, 500) as $chunk) {
                DB::table('saved_posts')->insertOrIgnore($chunk);
            }
        }

        DB::statement('UPDATE posts SET comments_count = (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)');
        DB::statement("UPDATE posts SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id)");
        foreach (['paw', 'love', 'haha', 'wow', 'sad', 'angry'] as $type) {
            $column = $type.'_count';

            if (Schema::hasColumn('posts', $column)) {
                DB::statement("UPDATE posts SET {$column} = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id AND reactions.type = '{$type}')");
            }
        }
        DB::statement("UPDATE comments SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id)");
        foreach (['paw', 'love'] as $type) {
            $column = $type.'_count';

            if (Schema::hasColumn('comments', $column)) {
                DB::statement("UPDATE comments SET {$column} = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id AND reactions.type = '{$type}')");
            }
        }
        DB::statement('UPDATE pets SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.pet_id = pets.id)');
        DB::statement('UPDATE users SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id)');
        if (Schema::hasColumn('users', 'scheduled_posts_count')) {
            DB::statement("UPDATE users SET scheduled_posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id AND posts.status = 'scheduled' AND posts.deleted_at IS NULL)");
        }
        if (Schema::hasColumn('users', 'post_reactions_received_count')) {
            DB::statement('UPDATE users SET post_reactions_received_count = (SELECT COALESCE(SUM(posts.reactions_count), 0) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }
        if (Schema::hasColumn('users', 'post_comments_received_count')) {
            DB::statement('UPDATE users SET post_comments_received_count = (SELECT COALESCE(SUM(posts.comments_count), 0) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }
        if (Schema::hasColumn('users', 'last_post_created_at')) {
            DB::statement('UPDATE users SET last_post_created_at = (SELECT MAX(posts.created_at) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }
        DB::statement('UPDATE hashtags SET posts_count = (SELECT COUNT(*) FROM post_hashtag WHERE post_hashtag.hashtag_id = hashtags.id)');
    }

    private function seedHashtags(): void
    {
        $timestamp = now();

        $tagNames = [
            'AdoptDontShop',
            'DogLife',
            'CatLife',
            'PetTips',
            'PuppyLove',
            'SeniorPets',
            'PetRescue',
            'HealthyPets',
            'TrainingDay',
            'PetFriendly',
            'VetVisit',
            'PlayTime',
            'PetCommunity',
            'LocalShelter',
            'FosterPet',
            'GroomingDay',
            'HappyTail',
            'PetParents',
            'WeekendWalk',
            'PetNutrition',
            'CatNap',
            'DogPark',
            'RescueStory',
            'PetHealth',
            'PetSafety',
            'NewPet',
            'PetEvent',
            'PetMarketplace',
            'PetTravel',
            'PetSocial',
        ];

        $rows = array_map(static fn (string $name): array => [
            'name' => $name,
            'slug' => Str::slug($name),
            'posts_count' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $tagNames);

        DB::table('hashtags')->insertOrIgnore($rows);
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
