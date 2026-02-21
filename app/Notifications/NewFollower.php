<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

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
            'actor_id' => $this->follower->id,
            'actor_name' => $this->follower->name,
            'actor_username' => $this->follower->username,
            'actor_avatar' => $this->follower->avatar_thumb,
            'message' => "@{$this->follower->username} started following you.",
            'action_url' => route('profile.show', $this->follower->username),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

}
