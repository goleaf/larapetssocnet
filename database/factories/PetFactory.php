<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pet>
 */
class PetFactory extends Factory
{
    protected $model = Pet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'slug' => null,
            'species' => fake()->randomElement(['dog', 'cat', 'bird', 'rabbit', 'hamster']),
            'breed' => fake()->optional(0.8)->word(),
            'sex' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->optional(0.85)->date(),
            'bio' => fake()->optional()->sentence(),
            'avatar_path' => fake()->optional(0.5)->imageUrl(640, 640, 'pets', true),
            'is_public' => true,
            'adoption_status' => 'not_listed',
            'followers_count' => 0,
            'posts_count' => 0,
            'is_archived' => false,
            'is_adoptable' => false,
            'adopted_at' => null,
            'adoption_listed_at' => null,
            'visibility' => 'public',
        ];
    }

    /**
     * Keep pet visible to everyone.
     */
    public function public(): static
    {
        return $this->state(fn (): array => [
            'is_public' => true,
            'visibility' => 'public',
            'is_archived' => false,
        ]);
    }

    /**
     * Keep pet visible to followers only.
     */
    public function followersOnly(): static
    {
        return $this->state(fn (): array => [
            'is_public' => false,
            'visibility' => 'followers_only',
            'is_archived' => false,
        ]);
    }

    /**
     * Hide pet from public profile surfaces.
     */
    public function private(): static
    {
        return $this->state(fn (): array => [
            'is_public' => false,
            'visibility' => 'private',
            'is_archived' => false,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'is_archived' => false,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'is_archived' => true,
            'is_public' => false,
            'visibility' => 'private',
        ]);
    }

    /**
     * Pet is visible for adoption and actively listed.
     */
    public function available(): static
    {
        return $this->state(fn (): array => [
            'adoption_status' => 'available',
            'is_adoptable' => true,
            'adoption_listed_at' => now(),
            'is_archived' => false,
        ]);
    }

    public function pendingAdoption(): static
    {
        return $this->state(fn (): array => [
            'adoption_status' => 'pending',
            'is_adoptable' => true,
            'adoption_listed_at' => now(),
            'is_archived' => false,
        ]);
    }

    public function adopted(): static
    {
        return $this->state(fn (): array => [
            'adoption_status' => 'adopted',
            'is_adoptable' => false,
            'adopted_at' => now(),
            'is_archived' => false,
        ]);
    }

    public function notListedForAdoption(): static
    {
        return $this->state(fn (): array => [
            'adoption_status' => 'not_listed',
            'adoption_listed_at' => null,
            'is_adoptable' => false,
        ]);
    }

    /**
     * Persist avatar for components that assert media path shape.
     */
    public function withAvatar(): static
    {
        return $this->state(fn (): array => [
            'avatar_path' => 'pets/'.fake()->unique()->uuid().'.jpg',
        ]);
    }

    /**
     * Persist cover media fields for component-oriented tests.
     */
    public function withCover(): static
    {
        return $this->state(fn (): array => [
            'cover_photo_path' => 'pet-covers/'.fake()->unique()->uuid().'.jpg',
            'cover_photo_position' => 45.0,
        ]);
    }

    /**
     * Add follower relationships for media-rich user flows.
     */
    public function withFollowers(int $count = 2): static
    {
        return $this->afterCreating(function (Pet $pet) use ($count): void {
            $followerIds = User::factory()
                ->count($count)
                ->create()
                ->pluck('id')
                ->all();

            $pet->followers()->syncWithoutDetaching($followerIds);
        });
    }
}
