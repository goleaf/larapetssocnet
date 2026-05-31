<?php

namespace Database\Factories\Analytics;

use App\Models\Analytics\ProfileView;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileView>
 */
class ProfileViewFactory extends Factory
{
    protected $model = ProfileView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_user_id' => User::factory(),
            'viewer_user_id' => User::factory(),
            'viewed_on' => fake()->date(),
            'views_count' => fake()->numberBetween(1, 20),
        ];
    }
}
