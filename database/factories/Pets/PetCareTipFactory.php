<?php

namespace Database\Factories\Pets;

use App\Models\Identity\User;
use App\Models\Pets\PetCareTip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PetCareTip>
 */
class PetCareTipFactory extends Factory
{
    protected $model = PetCareTip::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence();

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'species' => fake()->optional(0.8)->randomElement(['dog', 'cat', 'rabbit', 'bird']),
            'category' => fake()->optional(0.6)->randomElement(['nutrition', 'training', 'health', 'exercise']),
            'content' => fake()->paragraph(),
            'is_approved' => fake()->boolean(10),
            'helpful_count' => 0,
        ];
    }
}
