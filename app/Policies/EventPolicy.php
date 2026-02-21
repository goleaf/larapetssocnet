<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;

class EventPolicy
{
    public function view(?User $user, Event $event): bool
    {
        if (! $event->group_id) {
            return true;
        }

        $group = Group::query()->find($event->group_id);
        if (! $group) {
            return true;
        }

        return app(GroupPolicy::class)->view($user, $group);
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, Event $event): bool
    {
        return (int) ($event->creator_user_id ?? 0) === (int) $user->getKey()
            || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function attend(User $user, Event $event): bool
    {
        return $this->view($user, $event);
    }
}
