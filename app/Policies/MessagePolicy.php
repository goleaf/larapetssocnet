<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        return (int) $message->sender_user_id === (int) $user->getKey()
            || (int) $message->recipient_user_id === (int) $user->getKey();
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
        return (int) $message->sender_user_id === (int) $user->getKey();
    }
}
