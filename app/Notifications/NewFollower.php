<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class NewFollower extends Notification
{
    use Queueable;

    public function __construct(public readonly User $follower) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_follower',
            'message' => $this->follower->name.' started following you.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->follower->id,
            'actor_name' => $this->follower->name,
            'actor_username' => $this->follower->username,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('profile.show')) {
            return route('profile.show', ['user' => $this->follower]);
        }

        return url('/');
    }
}
