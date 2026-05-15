<?php

namespace Database\Seeders;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Carbon\Carbon;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
        DB::statement('UPDATE pets SET posts_count = (SELECT COUNT(*) FROM posts WHERE posts.pet_id = pets.id)');
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
