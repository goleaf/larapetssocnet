<?php

namespace Database\Factories;

use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => function (array $attributes): int {
                $conversation = Conversation::query()->find((int) $attributes['conversation_id']);

                return (int) ($conversation?->user_one_id ?? User::factory()->create()->getKey());
            },
            'receiver_id' => function (array $attributes): int {
                $conversation = Conversation::query()->find((int) $attributes['conversation_id']);

                return (int) ($conversation?->user_two_id ?? User::factory()->create()->getKey());
            },
            'body' => $this->faker->sentence(),
            'status' => MessageStatus::Sent->value,
            'is_read' => false,
            'read_at' => null,
        ];
    }
}
