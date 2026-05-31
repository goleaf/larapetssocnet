<?php

namespace Database\Factories\Moderation;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_user_id' => User::factory(),
            'reportable_type' => Post::class,
            'reportable_id' => Post::factory(),
            'reason' => fake()->randomElement([
                Report::REASON_SPAM,
                Report::REASON_HARASSMENT,
                Report::REASON_MISINFORMATION,
                Report::REASON_OTHER,
            ]),
            'details' => fake()->optional(0.8)->sentence(),
            'status' => Report::STATUS_PENDING,
            'priority' => Report::PRIORITY_NORMAL,
        ];
    }
}
