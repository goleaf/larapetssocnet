<?php

namespace Database\Factories\Pets;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PetMilestone>
 */
class PetMilestoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = fake()->optional()->sentence();

        return [
            'pet_id' => Pet::factory(),
            'user_id' => User::factory(),
            'post_id' => null,
            'milestone_type' => fake()->randomElement(PetMilestone::TYPES),
            'title' => fake()->sentence(4),
            'body' => $body,
            'body_html' => $body ? '<p>'.e($body).'</p>' : null,
            'occurred_on' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'share_as_post' => false,
        ];
    }

    public function sharedAsPost(): self
    {
        return $this->state(fn (): array => [
            'post_id' => Post::factory(),
            'share_as_post' => true,
        ]);
    }
}
