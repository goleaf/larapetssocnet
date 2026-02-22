<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        if ((int) $message->sender_id === (int) $user->getKey()) {
            return true;
        }

        $conversation = $message->conversation;

        if (! $conversation instanceof Conversation) {
            return false;
        }

        return (int) $conversation->user_one_id === (int) $user->getKey()
            || (int) $conversation->user_two_id === (int) $user->getKey();
    }

    public function create(User $user, User $recipient): bool
    {
        if ((int) $user->getKey() === (int) $recipient->getKey()) {
            return false;
        }

        return ! $user->hasBlocked($recipient) && ! $recipient->hasBlocked($user);
    }

    public function delete(User $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->getKey();
    }
}
