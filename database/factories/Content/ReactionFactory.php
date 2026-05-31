<?php

namespace Database\Factories\Content;

use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Models\Content\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reaction>
 */
class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(Reaction::types()),
            'reactable_type' => Post::class,
            'reactable_id' => Post::factory(),
        ];
    }

    /**
     * Force paw reaction type.
     */
    public function paw(): static
    {
        return $this->state(fn (): array => [
            'type' => Reaction::TYPE_PAW,
        ]);
    }

    /**
     * Force love reaction type.
     */
    public function love(): static
    {
        return $this->state(fn (): array => [
            'type' => Reaction::TYPE_LOVE,
        ]);
    }

    /**
     * Force happy reaction type.
     */
    public function haha(): static
    {
        return $this->state(fn (): array => [
            'type' => Reaction::TYPE_HAHA,
        ]);
    }

    /**
     * Attach this reaction to a post.
     */
    public function forPost(): static
    {
        return $this->state(fn (): array => [
            'reactable_type' => Post::class,
            'reactable_id' => Post::factory(),
        ]);
    }

    /**
     * Attach this reaction to a comment.
     */
    public function forComment(): static
    {
        return $this->state(fn (): array => [
            'reactable_type' => Comment::class,
            'reactable_id' => Comment::factory(),
        ]);
    }
}
