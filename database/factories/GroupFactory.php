<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(2, 4), true);
        $type = fake()->randomElement(['public', 'public', 'private', 'secret']);

        return [
            'owner_user_id' => User::factory(),
            'owner_id' => static fn (array $attributes): int => (int) $attributes['owner_user_id'],
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'description' => fake()->sentence(fake()->numberBetween(10, 18)),
            'description_html' => fake()->sentence(fake()->numberBetween(10, 18)),
            'rules' => fake()->optional(0.3)->sentence(fake()->numberBetween(6, 12)),
            'type' => $type,
            'privacy' => $type,
            'species_focus' => fake()->randomElement(['dog', 'cat', 'bird', 'rabbit', 'fish', 'reptile', 'all']),
            'location' => fake()->optional(0.65)->city().', '.fake()->stateAbbr(),
            'website' => fake()->optional(0.4)->url(),
            'avatar' => null,
            'cover_image' => null,
            'cover_image_path' => fake()->optional(0.35)->imageUrl(1200, 600, 'nature', true),
            'members_count' => 0,
            'posts_count' => 0,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (): array => [
            'type' => 'public',
            'privacy' => 'public',
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (): array => [
            'type' => 'private',
            'privacy' => 'private',
        ]);
    }

    public function secret(): static
    {
        return $this->state(fn (): array => [
            'type' => 'secret',
            'privacy' => 'secret',
        ]);
    }
}
