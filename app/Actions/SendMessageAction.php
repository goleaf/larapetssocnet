<?php

namespace App\Actions;

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
        if ((int) $sender->getKey() === (int) $receiver->getKey()) {
            throw ValidationException::withMessages([
                'receiver_id' => [__('messages.validation.self_message')],
            ]);
        }

        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => [__('messages.validation.required_body')],
            ]);
        }

        $message = DB::transaction(function () use ($sender, $receiver, $body): Message {
            $conversation = $this->resolveConversation($sender, $receiver);

            Message::query()
                ->where('sender_id', $receiver->getKey())
                ->where('receiver_id', $sender->getKey())
                ->whereNull('read_at')
                ->update([
                    'status' => MessageStatus::Delivered->value,
                ]);

            return Message::query()->create([
                'conversation_id' => $conversation->getKey(),
                'sender_id' => $sender->getKey(),
                'receiver_id' => $receiver->getKey(),
                'body' => $body,
                'status' => MessageStatus::Sent->value,
            ]);
        });

        MessageSent::dispatch($message, $sender, $receiver);

        Cache::forget($this->unreadCacheKey($receiver));

        return $message->load([
            'sender:id,name,username,avatar_path',
            'receiver:id,name,username,avatar_path',
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

    private function unreadCacheKey(User $user): string
    {
        return 'msg_unread:'.$user->getKey();
    }
}
