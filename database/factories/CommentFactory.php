<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->sentence();

        return [
            'post_id' => Post::factory(),
            'user_id' => User::factory(),
            'parent_id' => null,
            'body' => $body,
            'body_html' => $body,
            'gif_url' => null,
            'gif_preview_url' => null,
            'gif_title' => null,
            'gif_provider' => null,
            'language_code' => 'en',
            'quality_score' => 0,
            'depth' => 0,
            'is_pinned' => false,
            'edit_count' => 0,
            'replies_count' => 0,
            'reactions_count' => 0,
            'paw_count' => 0,
            'love_count' => 0,
        ];
    }

    /**
     * Create a top-level comment state.
     */
    public function topLevel(): static
    {
        return $this->state(fn (): array => [
            'parent_id' => null,
            'depth' => 0,
        ]);
    }

    /**
     * Create a reply comment tied to a parent row.
     */
    public function reply(): static
    {
        return $this->afterCreating(function (Comment $comment): void {
            $parent = Comment::factory()->create([
                'post_id' => $comment->post_id,
                'user_id' => $comment->user_id,
                'parent_id' => null,
                'depth' => 0,
            ]);

            $comment->forceFill([
                'parent_id' => $parent->getKey(),
                'depth' => 1,
            ])->save();
        });
    }

    /**
     * Attach reaction rows to this comment.
     */
    public function withReactions(int $count = 2): static
    {
        return $this->afterCreating(function (Comment $comment) use ($count): void {
            Reaction::factory()
                ->count($count)
                ->forComment()
                ->create([
                    'reactable_id' => $comment->getKey(),
                    'type' => Reaction::TYPE_PAW,
                    'user_id' => $comment->user_id,
                ]);
        });
    }

    /**
     * Mark the comment as pinned.
     */
    public function pinned(): static
    {
        return $this->state(fn (): array => [
            'is_pinned' => true,
        ]);
    }
}
