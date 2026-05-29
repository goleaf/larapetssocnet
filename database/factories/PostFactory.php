<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model>
     */
    protected $model = Post::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Post $post): void {
            $updates = [];

            if ($post->author_type === null) {
                $updates['author_type'] = User::class;
            }

            if ($post->author_id === null) {
                $updates['author_id'] = $post->user_id;
            }

            if ($post->status === PostStatus::Scheduled && $post->scheduled_publish_at === null) {
                $updates['scheduled_publish_at'] = $post->published_at;
            }

            if ($updates !== []) {
                $post->updateQuietly($updates);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = $this->faker->paragraphs(3, true);

        return [
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'author_type' => User::class,
            'author_id' => null,
            'pet_id' => null,
            'body' => $body,
            'body_html' => '<p>'.e($body).'</p>',
            'type' => 'text',
            'status' => 'published',
            'published_at' => now(),
            'scheduled_publish_at' => null,
            'visibility' => Post::VISIBILITY_PUBLIC,
            'mood' => null,
            'location' => $this->faker->optional(0.3)->city(),
            'location_display_text' => null,
            'is_pinned' => false,
            'pinned_at' => null,
            'edited_at' => null,
            'edit_count' => 0,
            'likes_count' => 0,
            'comments_count' => 0,
            'reactions_count' => 0,
            'love_count' => 0,
            'cute_count' => 0,
            'funny_count' => 0,
            'wow_count' => 0,
            'sad_count' => 0,
            'support_count' => 0,
            'shares_count' => 0,
            'view_count' => 0,
            'save_count' => 0,
            'metadata' => null,
            'link_preview' => null,
            'original_post_id' => null,
            'quote_post_id' => null,
        ];
    }
}
