<?php

namespace App\Observers;

use App\Enums\MessageStatus;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use Illuminate\Support\Facades\Cache;
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
            $conversation->increment('user_two_unread_count');
        } elseif ((int) $message->sender_id === (int) $conversation->user_two_id) {
            $conversation->increment('user_one_unread_count');
        }

        Conversation::query()
            ->whereKey($conversation->getKey())
            ->update($updates);

        $this->bustUnreadCache($message);
    }

    public function updated(Message $message): void
    {
        if ($message->wasChanged(['read_at', 'status'])) {
            if ($message->read_at !== null && $message->status !== MessageStatus::Read) {
                $message->status = MessageStatus::Read;
                $message->saveQuietly();
            }

            $this->bustUnreadCache($message);
        }
    }

    public function deleted(Message $message): void
    {
        $this->bustUnreadCache($message);
    }

    public function restored(Message $message): void
    {
        $this->bustUnreadCache($message);
    }

    private function bustUnreadCache(Message $message): void
    {
        if ($message->sender_id) {
            Cache::forget('msg_unread:'.$message->sender_id);
        }

        if ($message->receiver_id) {
            Cache::forget('msg_unread:'.$message->receiver_id);
        }
    }
}
