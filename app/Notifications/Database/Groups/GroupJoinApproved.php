<?php

namespace App\Notifications\Database\Groups;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class GroupJoinApproved extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly User $approver,
        public readonly Group $group,
    ) {}

    /**
     * @return list<string>
     */
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
            'type' => 'group_join_approved',
            'message' => $this->approver->name.' approved your request to join '.$this->group->name.'.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->approver->id,
            'actor_name' => $this->approver->name,
            'actor_username' => $this->approver->username,
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('groups.show')) {
            return route('groups.show', ['group' => $this->group]);
        }

        if (Route::has('explore.index')) {
            return route('explore.index');
        }

        return url('/');
    }
}
