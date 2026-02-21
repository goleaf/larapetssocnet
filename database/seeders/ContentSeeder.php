<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    private const TARGET_POST_COUNT = 320;

    /**
     * Seed content data.
     */
    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();

        if ($userIds === []) {
            return;
        }

        $faker = fake();

        $this->seedHashtags();

        $hashtagIds = DB::table('hashtags')->pluck('id')->all();
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
                'visibility' => $faker->randomElement(['public', 'followers', 'private']),
                'status' => 'published',
                'comments_count' => 0,
                'reactions_count' => 0,
                'published_at' => $createdAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $postHashtags = [];

        if ($hashtagIds !== []) {
            foreach ($postIds as $postId) {
                $maxTags = min(4, count($hashtagIds));
                $minTags = min(1, $maxTags);
                $tagCount = random_int($minTags, $maxTags);

                foreach ($this->pickRandomUnique($hashtagIds, $tagCount) as $hashtagId) {
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
        $reactionTypes = ['like', 'love', 'wow', 'care'];

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

        if ($postIds !== []) {
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
        }

        if ($savedPosts !== []) {
            foreach (array_chunk($savedPosts, 500) as $chunk) {
                DB::table('saved_posts')->insertOrIgnore($chunk);
            }
        }

        DB::statement('UPDATE posts SET comments_count = (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)');
        DB::statement("UPDATE posts SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id)");
        DB::statement("UPDATE comments SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id)");
        DB::statement('UPDATE pets SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.pet_id = pets.id)');
        DB::statement('UPDATE users SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id)');
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

        $rows = array_map(static fn (string $name) => [
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
