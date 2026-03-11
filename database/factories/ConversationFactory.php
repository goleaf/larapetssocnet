<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => null,
            'user_one_id' => User::factory(),
            'user_two_id' => User::factory(),
            'blocked_by' => null,
            'last_message_at' => null,
            'last_message_preview' => null,
            'user_one_unread_count' => 0,
            'user_two_unread_count' => 0,
        ];
    }
}
