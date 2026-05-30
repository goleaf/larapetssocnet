<?php

declare(strict_types=1);

namespace App\Notifications\Database\Social;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Models\Identity\User;
use Illuminate\Notifications\Notification;

class NewFollowRequest extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(public readonly User $requester) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'follow_request',
            'actor_id' => $this->requester->id,
            'actor_name' => $this->requester->name,
            'actor_username' => $this->requester->username,
            'actor_avatar' => $this->requester->avatar_thumb,
            'message' => "@{$this->requester->username} wants to follow you.",
            'action_url' => route('follow-requests.index'),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
