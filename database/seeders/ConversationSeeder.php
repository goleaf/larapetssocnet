<?php

namespace Database\Seeders;

use App\Enums\MessageStatus;
use App\Models\Identity\User;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use Carbon\CarbonImmutable;
use Faker\Generator;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    private const MIN_MESSAGES_PER_CONVERSATION = 4;

    private const MAX_MESSAGES_PER_CONVERSATION = 9;

    /**
     * @var list<int>
     */
    private const PAIR_OFFSETS = [1, 2];

    public function run(): void
    {
        $userIds = User::query()
            ->select(['id'])
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();

        if (count($userIds) < 2) {
            return;
        }

        $faker = fake();
        $faker->seed(20260312);

        $pairs = $this->buildUserPairs($userIds);

        foreach ($pairs as $pairIndex => [$userOneId, $userTwoId]) {
            $conversationStartedAt = CarbonImmutable::now()
                ->subDays(45)
                ->addMinutes(($pairIndex * 40) + $faker->numberBetween(0, 20));

            $conversation = Conversation::query()->firstOrCreate(
                [
                    'user_one_id' => $userOneId,
                    'user_two_id' => $userTwoId,
                ],
                [
                    'last_message_at' => $conversationStartedAt,
                    'last_message_preview' => null,
                    'user_one_unread_count' => 0,
                    'user_two_unread_count' => 0,
                    'blocked_by' => null,
                    'created_at' => $conversationStartedAt,
                    'updated_at' => $conversationStartedAt,
                ]
            );

            if ($conversation->messages()->exists()) {
                continue;
            }

            $summary = $this->seedConversationMessages(
                conversation: $conversation,
                userOneId: $userOneId,
                userTwoId: $userTwoId,
                startedAt: $conversationStartedAt,
                faker: $faker,
            );

            $conversation->update($summary);
        }
    }

    /**
     * @param  list<int>  $userIds
     * @return list<array{0: int, 1: int}>
     */
    private function buildUserPairs(array $userIds): array
    {
        $pairs = [];
        $pairIndex = [];
        $userCount = count($userIds);

        foreach (self::PAIR_OFFSETS as $offset) {
            if ($offset >= $userCount) {
                continue;
            }

            for ($index = 0; $index < $userCount; $index++) {
                $this->appendPair(
                    pairs: $pairs,
                    pairIndex: $pairIndex,
                    firstUserId: $userIds[$index],
                    secondUserId: $userIds[($index + $offset) % $userCount],
                );
            }
        }

        return $pairs;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $pairs
     * @param  array<string, true>  $pairIndex
     */
    private function appendPair(array &$pairs, array &$pairIndex, int $firstUserId, int $secondUserId): void
    {
        if ($firstUserId === $secondUserId) {
            return;
        }

        $userOneId = min($firstUserId, $secondUserId);
        $userTwoId = max($firstUserId, $secondUserId);
        $pairKey = $userOneId.':'.$userTwoId;

        if (isset($pairIndex[$pairKey])) {
            return;
        }

        $pairIndex[$pairKey] = true;
        $pairs[] = [$userOneId, $userTwoId];
    }

    /**
     * @return array{
     *   last_message_at: CarbonImmutable,
     *   last_message_preview: string,
     *   user_one_unread_count: int,
     *   user_two_unread_count: int
     * }
     */
    private function seedConversationMessages(
        Conversation $conversation,
        int $userOneId,
        int $userTwoId,
        CarbonImmutable $startedAt,
        Generator $faker,
    ): array {
        $messageCount = $faker->numberBetween(
            self::MIN_MESSAGES_PER_CONVERSATION,
            self::MAX_MESSAGES_PER_CONVERSATION,
        );

        $messageAt = $startedAt;
        $lastBody = '';
        $userOneUnreadCount = 0;
        $userTwoUnreadCount = 0;

        for ($index = 0; $index < $messageCount; $index++) {
            $senderId = $index % 2 === 0 ? $userOneId : $userTwoId;
            $receiverId = $senderId === $userOneId ? $userTwoId : $userOneId;
            $messageAt = $messageAt->addMinutes($faker->numberBetween(4, 100));

            $isRead = $index < $messageCount - 1 && $faker->boolean(70);
            $status = $isRead ? MessageStatus::Read->value : MessageStatus::Delivered->value;
            $body = $faker->sentence($faker->numberBetween(5, 12));

            $lastBody = $body;

            Message::query()->create([
                'conversation_id' => $conversation->getKey(),
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'body' => $body,
                'status' => $status,
                'is_read' => $isRead,
                'read_at' => $isRead ? $messageAt : null,
                'created_at' => $messageAt,
                'updated_at' => $messageAt,
            ]);

            if (! $isRead) {
                if ($receiverId === $userOneId) {
                    $userOneUnreadCount++;
                } else {
                    $userTwoUnreadCount++;
                }
            }
        }

        return [
            'last_message_at' => $messageAt,
            'last_message_preview' => mb_substr($lastBody, 0, 100),
            'user_one_unread_count' => $userOneUnreadCount,
            'user_two_unread_count' => $userTwoUnreadCount,
        ];
    }
}
