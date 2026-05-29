<?php

namespace Database\Factories\Content;

use App\Models\Content\PostTemplate;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostTemplate>
 */
class PostTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'template_text' => fake()->paragraph(),
        ];
    }
}
