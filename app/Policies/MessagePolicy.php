<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Identity\User;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;

class MessagePolicy
{
    public function view(User $user, Message $message): bool
    {
        if ((int) $message->sender_id === (int) $user->getKey() || (int) ($message->receiver_id ?? 0) === (int) $user->getKey()) {
            return true;
        }

        $conversation = $message->conversation;

        if (! $conversation instanceof Conversation) {
            return false;
        }

        return (int) $conversation->user_one_id === (int) $user->getKey()
            || (int) $conversation->user_two_id === (int) $user->getKey();
    }

    public function viewThread(User $user, User $peer): bool
    {
        if ((int) $user->getKey() === (int) $peer->getKey()) {
            return false;
        }

        return ! $user->hasBlocked($peer) && ! $peer->hasBlocked($user);
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
