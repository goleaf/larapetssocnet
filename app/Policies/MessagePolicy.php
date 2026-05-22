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
        return $this->canMessagePeer($user, $peer);
    }

    public function create(User $user, User $recipient): bool
    {
        return $this->canMessagePeer($user, $recipient);
    }

    public function delete(User $user, Message $message): bool
    {
        return (int) $message->sender_id === (int) $user->getKey();
    }

    private function canMessagePeer(User $user, User $peer): bool
    {
        if ((int) $user->getKey() === (int) $peer->getKey()) {
            return false;
        }

        if ($user->hasBlocked($peer) || $peer->hasBlocked($user)) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        return $user->isFollowing($peer) && $peer->isFollowing($user);
    }
}
