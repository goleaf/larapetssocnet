<?php

namespace App\Actions\Messaging;

use App\Enums\MessageStatus;
use App\Events\MessageSent;
use App\Models\Identity\User;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SendMessageAction
{
    /**
     * @param  array{body?: string}  $data
     */
    public function handle(User $sender, User $receiver, array $data): Message
    {
        $this->ensureCanSend($sender, $receiver);
        $body = $this->normalizeBody($data);

        $message = DB::transaction(fn (): Message => $this->createMessage($sender, $receiver, $body));

        MessageSent::dispatch($message, $sender, $receiver);

        Cache::forget($this->unreadCacheKey($receiver));

        return $this->loadMessageRelations($message);
    }

    private function ensureCanSend(User $sender, User $receiver): void
    {
        if ((int) $sender->getKey() === (int) $receiver->getKey()) {
            throw ValidationException::withMessages([
                'receiver_id' => [__('messages.validation.self_message')],
            ]);
        }

        if ($sender->hasBlockingRelationshipWith($receiver)) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Messaging is unavailable because one user has blocked the other.'],
            ]);
        }

        if (! $sender->hasAnyRole(['admin', 'moderator']) && (! $sender->isFollowing($receiver) || ! $receiver->isFollowing($sender))) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Messaging is available only when both users follow each other.'],
            ]);
        }
    }

    /**
     * @param  array{body?: string}  $data
     */
    private function normalizeBody(array $data): string
    {
        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => [__('messages.validation.required_body')],
            ]);
        }

        return $body;
    }

    private function createMessage(User $sender, User $receiver, string $body): Message
    {
        $conversation = $this->resolveConversation($sender, $receiver);

        $this->markIncomingMessagesDelivered($sender, $receiver);

        return Message::query()->create([
            'conversation_id' => $conversation->getKey(),
            'sender_id' => $sender->getKey(),
            'receiver_id' => $receiver->getKey(),
            'body' => $body,
            'status' => MessageStatus::Sent->value,
        ]);
    }

    private function markIncomingMessagesDelivered(User $sender, User $receiver): void
    {
        Message::query()
            ->where('sender_id', $receiver->getKey())
            ->where('receiver_id', $sender->getKey())
            ->whereNull('read_at')
            ->update([
                'status' => MessageStatus::Delivered->value,
            ]);
    }

    private function resolveConversation(User $sender, User $receiver): Conversation
    {
        $userOneId = min((int) $sender->getKey(), (int) $receiver->getKey());
        $userTwoId = max((int) $sender->getKey(), (int) $receiver->getKey());

        return Conversation::query()->firstOrCreate([
            'user_one_id' => $userOneId,
            'user_two_id' => $userTwoId,
        ], [
            'user_one_unread_count' => 0,
            'user_two_unread_count' => 0,
        ]);
    }

    private function loadMessageRelations(Message $message): Message
    {
        return $message->load([
            'sender:id,name,username,avatar_path',
            'receiver:id,name,username,avatar_path',
        ]);
    }

    private function unreadCacheKey(User $user): string
    {
        return 'msg_unread:'.$user->getKey();
    }
}
