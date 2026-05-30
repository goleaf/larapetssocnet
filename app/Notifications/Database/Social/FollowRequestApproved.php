<?php

declare(strict_types=1);

namespace App\Notifications\Database\Social;

use App\Models\Identity\User;
use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class FollowRequestApproved extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(public readonly User $approver) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'follow_request_approved',
            'actor_id' => $this->approver->id,
            'actor_name' => $this->approver->name,
            'actor_username' => $this->approver->username,
            'actor_avatar' => $this->approver->avatar_thumb,
            'message' => "@{$this->approver->username} approved your follow request.",
            'action_url' => route('profile.show', $this->approver->username),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
