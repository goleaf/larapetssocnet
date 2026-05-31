<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Support\Seeding\SeedProfile;
use Carbon\Carbon;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostSeeder extends Seeder
{
    private const TARGET_POST_COUNT = 100;

    /**
     * @var list<string>
     */
    private const PET_SUBJECTS = [
        'My dog',
        'My cat',
        'My puppy',
        'My kitten',
        'My rescue pup',
        'My senior cat',
        'Our rabbit',
        'Our bird',
    ];

    /**
     * @var list<string>
     */
    private const PET_ACTIONS = [
        'chased a squeaky toy',
        'napped in a sunbeam',
        'learned a new trick',
        'went on a long walk',
        'made a tiny mess',
        'waited by the treat jar',
        'zoomed through the living room',
        'posed for a photo',
    ];

    /**
     * @var list<string>
     */
    private const PET_PLACES = [
        'park',
        'backyard',
        'living room',
        'vet office',
        'kitchen',
        'balcony',
        'pet store',
        'favorite blanket',
    ];

    /**
     * @var list<string>
     */
    private const COMMENT_BODIES = [
        'So cute. Thanks for sharing your pet update.',
        'Love this moment, your pet looks very happy.',
        'This made my day. Please post more pet photos soon.',
        'Great tip, I will try this with my dog too.',
        'Your cat has the best expression in this post.',
        'What a sweet update. Pets really make life better.',
        'Adorable post. Your pet looks healthy and playful.',
        'This is wholesome content. Give your pet extra treats.',
    ];

    public function run(): void
    {
        $profile = SeedProfile::fromConfig();

        if ($profile === null) {
            $this->runLegacy();

            return;
        }

        $this->runProfile($profile);
    }

    private function runLegacy(): void
    {
        $users = User::query()
            ->with([
                'pets' => static function ($query): void {
                    $query
                        ->without(['user', 'species', 'breed', 'media', 'tags'])
                        ->select(['id', 'user_id']);
                },
            ])
            ->orderBy('id')
            ->get(['id']);

        if ($users->isEmpty()) {
            return;
        }

        $faker = fake();
        $faker->seed(20260224);
        mt_srand(20260224);

        $posts = collect();

        for ($index = 0; $index < self::TARGET_POST_COUNT; $index++) {
            $user = $users[$index % $users->count()];
            $petIds = $user->pets->pluck('id')->all();
            $petId = null;

            if ($petIds !== [] && $faker->boolean(70)) {
                $petId = $petIds[$faker->numberBetween(0, count($petIds) - 1)];
            }

            $body = $this->makePetBody($faker);
            $createdAt = Carbon::instance($faker->dateTimeBetween('-45 days', 'now'));

            $posts->push(
                Post::factory()
                    ->for($user)
                    ->create([
                        'pet_id' => $petId,
                        'body' => $body,
                        'body_html' => '<p>'.e($body).'</p>',
                        'type' => Post::TYPE_TEXT,
                        'visibility' => Post::VISIBILITY_PUBLIC,
                        'location' => null,
                        'likes_count' => 0,
                        'comments_count' => 0,
                        'shares_count' => 0,
                        'is_pinned' => false,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ])
            );
        }

        $userIds = $users->pluck('id')->all();

        foreach ($posts as $post) {
            $commentCount = $faker->numberBetween(3, 8);

            for ($index = 0; $index < $commentCount; $index++) {
                $createdAt = Carbon::instance($faker->dateTimeBetween($post->created_at, 'now'));

                Comment::factory()
                    ->for($post)
                    ->create([
                        'user_id' => $userIds[$faker->numberBetween(0, count($userIds) - 1)],
                        'parent_id' => null,
                        'body' => $faker->randomElement(self::COMMENT_BODIES),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
            }
        }

        $postIds = $posts->pluck('id')->all();
        $likeRows = [];

        foreach ($userIds as $userId) {
            $candidatePostIds = $postIds;
            shuffle($candidatePostIds);

            $likesCount = $faker->numberBetween(10, min(30, count($candidatePostIds)));

            foreach (array_slice($candidatePostIds, 0, $likesCount) as $postId) {
                $createdAt = Carbon::instance($faker->dateTimeBetween('-30 days', 'now'));

                $likeRows[] = [
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'created_at' => $createdAt,
                ];
            }
        }

        foreach (array_chunk($likeRows, 500) as $chunk) {
            DB::table('likes')->insertOrIgnore($chunk);
        }

        DB::statement('UPDATE posts SET comments_count = (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)');
        DB::statement('UPDATE posts SET likes_count = (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id)');
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
        DB::statement('UPDATE pets SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.pet_id = pets.id)');
    }

    private function runProfile(SeedProfile $profile): void
    {
        $users = User::query()
            ->with([
                'pets' => static function ($query): void {
                    $query
                        ->without(['user', 'species', 'breed', 'media', 'tags'])
                        ->select(['id', 'user_id']);
                },
            ])
            ->orderBy('id')
            ->get(['id']);

        if ($users->isEmpty()) {
            return;
        }

        $targetPostCount = max(0, $profile->posts());
        $targetCommentCount = max(0, $profile->comments());
        $targetReactionCount = max(0, $profile->likes());

        if ($targetPostCount === 0) {
            return;
        }

        $userIds = $users->pluck('id')->all();
        $userCount = count($userIds);
        $postIds = [];
        $createdBase = now()->subDays(35);

        $petIdsByUser = $users
            ->mapWithKeys(static function (User $user) {
                return [
                    (int) $user->getKey() => $user->pets
                        ->pluck('id')
                        ->map(fn (int $petId) => $petId)
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        for ($postIndex = 0; $postIndex < $targetPostCount; $postIndex++) {
            $user = $users[$postIndex % $userCount];
            $userId = (int) $user->getKey();
            $userPets = $petIdsByUser[$userId] ?? [];
            $petId = null;

            if ($userPets !== [] && $postIndex % 10 < 7) {
                $petId = $userPets[$postIndex % count($userPets)];
            }

            $createdAt = $createdBase->clone()->addMinutes($postIndex);
            $body = sprintf('[seed:%s] %s', $profile->value, $this->makeProfilePostBody($postIndex));

            $post = Post::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'body' => $body,
                ],
                [
                    'pet_id' => $petId,
                    'body_html' => '<p>'.e($body).'</p>',
                    'type' => Post::TYPE_TEXT,
                    'status' => 'published',
                    'visibility' => Post::VISIBILITY_PUBLIC,
                    'location' => null,
                    'likes_count' => 0,
                    'comments_count' => 0,
                    'reactions_count' => 0,
                    'shares_count' => 0,
                    'is_pinned' => false,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]
            );

            $postIds[] = (int) $post->getKey();
        }

        if ($postIds === []) {
            return;
        }

        DB::table('comments')->whereIn('post_id', $postIds)->delete();
        DB::table('likes')->whereIn('post_id', $postIds)->delete();
        DB::table('reactions')->where('reactable_type', (new Post)->getMorphClass())
            ->whereIn('reactable_id', $postIds)
            ->delete();

        $commentRows = [];
        $commentBase = now()->subDays(30);

        for ($commentIndex = 0; $commentIndex < $targetCommentCount; $commentIndex++) {
            $postId = $postIds[$commentIndex % count($postIds)];
            $userId = $userIds[$commentIndex % $userCount];
            $createdAt = $commentBase->clone()->addMinutes($commentIndex);
            $body = sprintf('[seed:%s] %s', $profile->value, $this->makeProfileCommentBody($commentIndex));

            $commentRows[] = [
                'post_id' => $postId,
                'user_id' => $userId,
                'parent_id' => null,
                'body' => $body,
                'reactions_count' => 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($commentRows) >= 500) {
                $this->insertCommentRows($commentRows);
                $commentRows = [];
            }
        }

        $this->insertCommentRows($commentRows);

        $likeRows = [];
        $reactionRows = [];
        $reactionTypes = Reaction::allowedTypes();
        $reactionTypeCount = $reactionTypes === [] ? 1 : count($reactionTypes);

        if ($reactionTypes === []) {
            $reactionTypes = [Reaction::TYPE_PAW];
        }

        for ($reactionIndex = 0; $reactionIndex < $targetReactionCount; $reactionIndex++) {
            $postId = $postIds[intdiv($reactionIndex, $userCount)] ?? $postIds[$reactionIndex % count($postIds)];
            $userId = $userIds[$reactionIndex % $userCount];
            $createdAt = $createdBase->clone()->addMinutes(200 + $reactionIndex);

            $likeRows[] = [
                'post_id' => $postId,
                'user_id' => $userId,
                'created_at' => $createdAt,
            ];

            $reactionRows[] = [
                'user_id' => $userId,
                'reactable_type' => (new Post)->getMorphClass(),
                'reactable_id' => $postId,
                'type' => $reactionTypes[$reactionIndex % $reactionTypeCount],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($likeRows, 500) as $chunk) {
            DB::table('likes')->insertOrIgnore($chunk);
        }

        foreach (array_chunk($reactionRows, 500) as $chunk) {
            DB::table('reactions')->insertOrIgnore($chunk);
        }

        DB::statement('UPDATE posts SET comments_count = (SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id)');
        DB::statement('UPDATE posts SET likes_count = (SELECT COUNT(*) FROM likes WHERE likes.post_id = posts.id)');
        DB::statement('UPDATE posts SET reactions_count = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = \''.(new Post)->getMorphClass().'\' AND reactions.reactable_id = posts.id)');
        DB::statement('UPDATE pets SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.pet_id = pets.id)');
        foreach (Reaction::allowedCommentTypes() as $type) {
            $column = $type.'_count';

            if (Schema::hasColumn('comments', $column)) {
                DB::statement("UPDATE comments SET {$column} = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Comment' AND reactions.reactable_id = comments.id AND reactions.type = '{$type}')");
            }
        }
        foreach (Reaction::allowedTypes() as $type) {
            $column = $type.'_count';

            if (Schema::hasColumn('posts', $column)) {
                DB::statement("UPDATE posts SET {$column} = (SELECT COUNT(*) FROM reactions WHERE reactions.reactable_type = 'App\\Models\\Post' AND reactions.reactable_id = posts.id AND reactions.type = '{$type}')");
            }
        }

        DB::statement('UPDATE users SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id)');

        if (Schema::hasColumn('users', 'post_reactions_received_count')) {
            DB::statement('UPDATE users SET post_reactions_received_count = (SELECT COALESCE(SUM(posts.reactions_count), 0) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }

        if (Schema::hasColumn('users', 'post_comments_received_count')) {
            DB::statement('UPDATE users SET post_comments_received_count = (SELECT COALESCE(SUM(posts.comments_count), 0) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }

        if (Schema::hasColumn('users', 'last_post_created_at')) {
            DB::statement('UPDATE users SET last_post_created_at = (SELECT MAX(posts.created_at) FROM posts WHERE posts.user_id = users.id AND posts.deleted_at IS NULL)');
        }

        DB::statement('UPDATE users SET scheduled_posts_count = (SELECT COUNT(*) FROM posts WHERE posts.user_id = users.id AND posts.status = \'scheduled\' AND posts.deleted_at IS NULL)');
    }

    private function makeProfilePostBody(int $index): string
    {
        $sentenceCount = ($index % 3) + 1;
        $sentences = [];

        for ($sentenceIndex = 0; $sentenceIndex < $sentenceCount; $sentenceIndex++) {
            $sentences[] = sprintf(
                'Post %d: %s %s in the %s.',
                $index + 1,
                self::PET_SUBJECTS[($index + $sentenceIndex) % count(self::PET_SUBJECTS)],
                self::PET_ACTIONS[($index + $sentenceIndex) % count(self::PET_ACTIONS)],
                self::PET_PLACES[($index + ($sentenceIndex * 2)) % count(self::PET_PLACES)],
            );
        }

        return implode(' ', $sentences);
    }

    private function makeProfileCommentBody(int $index): string
    {
        return self::COMMENT_BODIES[$index % count(self::COMMENT_BODIES)];
    }

    private function insertCommentRows(array $commentRows): void
    {
        if ($commentRows === []) {
            return;
        }

        foreach (array_chunk($commentRows, 500) as $chunk) {
            DB::table('comments')->insertOrIgnore($chunk);
        }
    }

    private function makePetBody(Generator $faker): string
    {
        $sentences = [];
        $sentenceCount = $faker->numberBetween(1, 3);

        for ($index = 0; $index < $sentenceCount; $index++) {
            $sentences[] = sprintf(
                '%s %s in the %s.',
                $faker->randomElement(self::PET_SUBJECTS),
                $faker->randomElement(self::PET_ACTIONS),
                $faker->randomElement(self::PET_PLACES),
            );
        }

        return implode(' ', $sentences);
    }
}
