<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConversationSeeder extends Seeder
{
    private const TARGET_CONVERSATION_COUNT = 60;

    private const TARGET_MESSAGES_PER_CONVERSATION = 8;

    public function run(): void
    {
        $userIds = DB::table('users')->pluck('id')->all();

        if (count($userIds) < 2) {
            return;
        }

        $faker = fake();
        $faker->seed(20260222);

        $conversationKeys = [];
        $conversationIds = [];

        $attempts = 0;
        while (count($conversationIds) < self::TARGET_CONVERSATION_COUNT && $attempts < self::TARGET_CONVERSATION_COUNT * 5) {
            $attempts++;
            $userOneId = $userIds[array_rand($userIds)];
            $userTwoId = $userIds[array_rand($userIds)];

            if ($userOneId === $userTwoId) {
                continue;
            }

            $key = min($userOneId, $userTwoId).':'.max($userOneId, $userTwoId);

            if (isset($conversationKeys[$key])) {
                continue;
            }

            $conversationKeys[$key] = true;
            $createdAt = Carbon::instance($faker->dateTimeBetween('-60 days', 'now'));

            $conversationIds[] = DB::table('conversations')->insertGetId([
                'user_one_id' => min($userOneId, $userTwoId),
                'user_two_id' => max($userOneId, $userTwoId),
                'last_message_at' => $createdAt,
                'last_message_preview' => null,
                'user_one_unread_count' => 0,
                'user_two_unread_count' => 0,
                'blocked_by' => null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $messages = [];

        foreach ($conversationIds as $conversationId) {
            $conversation = DB::table('conversations')->where('id', $conversationId)->first();
            $participants = [(int) $conversation->user_one_id, (int) $conversation->user_two_id];
            $messageCount = $faker->numberBetween(2, self::TARGET_MESSAGES_PER_CONVERSATION);

            $lastAt = Carbon::instance($faker->dateTimeBetween('-60 days', 'now'));

            for ($i = 0; $i < $messageCount; $i++) {
                $senderId = $participants[$i % 2];
                $sentAt = (clone $lastAt)->addMinutes($faker->numberBetween(1, 120));
                $lastAt = $sentAt;

                $messages[] = [
                    'conversation_id' => $conversationId,
                    'sender_id' => $senderId,
                    'body' => $faker->sentence($faker->numberBetween(4, 14)),
                    'is_read' => $faker->boolean(70),
                    'read_at' => $faker->boolean(60) ? $sentAt : null,
                    'created_at' => $sentAt,
                    'updated_at' => $sentAt,
                ];
            }

            DB::table('conversations')->where('id', $conversationId)->update([
                'last_message_at' => $lastAt,
                'last_message_preview' => mb_substr($messages[count($messages) - 1]['body'], 0, 100),
            ]);
        }

        foreach (array_chunk($messages, 500) as $chunk) {
            DB::table('messages')->insert($chunk);
        }
    }
}
