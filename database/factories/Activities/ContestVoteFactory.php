<?php

namespace Database\Factories\Activities;

use App\Models\Activities\ContestEntry;
use App\Models\Activities\ContestVote;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContestVote>
 */
class ContestVoteFactory extends Factory
{
    protected $model = ContestVote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entry_id' => ContestEntry::factory(),
            'user_id' => User::factory(),
        ];
    }
}
