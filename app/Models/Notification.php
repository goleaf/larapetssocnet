<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    use HasFactory;

    public function scopeUnread(Builder $query): Builder
    {
        return $query
            ->select([
                'notifications.id',
                'notifications.type',
                'notifications.notifiable_type',
                'notifications.notifiable_id',
                'notifications.data',
                'notifications.read_at',
                'notifications.created_at',
                'notifications.updated_at',
            ])
            ->whereNull('notifications.read_at');
    }

    public function scopeForUser(Builder $query, User|int $user): Builder
    {
        $userId = $user instanceof User ? (int) $user->getKey() : (int) $user;

        return $query
            ->select([
                'notifications.id',
                'notifications.type',
                'notifications.notifiable_type',
                'notifications.notifiable_id',
                'notifications.data',
                'notifications.read_at',
                'notifications.created_at',
                'notifications.updated_at',
            ])
            ->where('notifications.notifiable_type', User::class)
            ->where('notifications.notifiable_id', $userId);
    }
}
