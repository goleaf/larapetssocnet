<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MessageObserver
{
    public function created(Message $message): void
    {
        $conversation = $message->conversation;

        if (! $conversation) {
            return;
        }

        $preview = trim(preg_replace('/\s+/', ' ', (string) $message->body) ?? '');
        $updates = [
            'last_message_at' => $message->created_at ?? now(),
            'last_message_preview' => Str::limit($preview, 100, ''),
        ];

        if ((int) $message->sender_id === (int) $conversation->user_one_id) {
            $updates['user_two_unread_count'] = DB::raw('COALESCE(user_two_unread_count, 0) + 1');
        } elseif ((int) $message->sender_id === (int) $conversation->user_two_id) {
            $updates['user_one_unread_count'] = DB::raw('COALESCE(user_one_unread_count, 0) + 1');
        }

        Conversation::query()
            ->whereKey($conversation->getKey())
            ->update($updates);
    }
}
