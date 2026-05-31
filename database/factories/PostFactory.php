<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Models\Content\Comment;
use App\Models\Content\PostMedia;
use App\Support\Posts\PostContentHasher;
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
            'content_hash' => app(PostContentHasher::class)->hash($body),
            'body_html' => '<p>'.e($body).'</p>',
            'type' => 'text',
            'status' => PostStatus::Published->value,
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
            'paw_count' => 0,
            'love_count' => 0,
            'haha_count' => 0,
            'cute_count' => 0,
            'funny_count' => 0,
            'wow_count' => 0,
            'sad_count' => 0,
            'angry_count' => 0,
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

    /**
     * Draft content stays hidden and unscheduled.
     */
    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Draft->value,
            'published_at' => null,
            'scheduled_publish_at' => null,
        ]);
    }

    /**
     * Schedule a post to be published in the future.
     */
    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Scheduled->value,
            'published_at' => now(),
            'scheduled_publish_at' => now()->addHour(),
        ]);
    }

    /**
     * Ensure published status and visibility semantics are explicit.
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Published->value,
            'published_at' => now(),
            'scheduled_publish_at' => null,
        ]);
    }

    /**
     * Archive a visible post for historical retention.
     */
    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Archived->value,
            'published_at' => now(),
            'scheduled_publish_at' => null,
        ]);
    }

    /**
     * Scope a post to public visibility.
     */
    public function public(): static
    {
        return $this->state(fn (): array => [
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);
    }

    /**
     * Scope a post to friends visibility.
     */
    public function friends(): static
    {
        return $this->state(fn (): array => [
            'visibility' => Post::VISIBILITY_FRIENDS,
        ]);
    }

    /**
     * Scope a post to private visibility.
     */
    public function privateVisibility(): static
    {
        return $this->state(fn (): array => [
            'visibility' => Post::VISIBILITY_PRIVATE,
        ]);
    }

    /**
     * Attach comment rows for post-specific coverage.
     */
    public function withComments(int $count = 2): static
    {
        return $this->afterCreating(function (Post $post) use ($count): void {
            Comment::factory()
                ->count($count)
                ->create(['post_id' => $post->getKey(), 'user_id' => $post->user_id]);
        });
    }

    /**
     * Attach reaction rows for post-specific coverage.
     */
    public function withReactions(int $count = 2): static
    {
        return $this->afterCreating(function (Post $post) use ($count): void {
            Reaction::factory()
                ->count($count)
                ->forPost()
                ->create([
                    'reactable_id' => $post->getKey(),
                    'type' => Reaction::TYPE_PAW,
                    'user_id' => $post->user_id,
                ]);
        });
    }

    /**
     * Attach media records for post-specific coverage.
     */
    public function withImages(int $count = 2): static
    {
        return $this->afterCreating(function (Post $post) use ($count): void {
            PostMedia::factory()
                ->count($count)
                ->create(['post_id' => $post->getKey()]);
        });
    }

    /**
     * Alias for explicitness where callers expect media helper.
     */
    public function withMedia(int $count = 2): static
    {
        return $this->withImages($count);
    }
}
