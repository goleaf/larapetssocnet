<?php

namespace App\Notifications;

use App\Models\Groups\Group;
use App\Models\Identity\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class GroupJoinRequest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $requester,
        public readonly Group $group,
    ) {
        $this->afterCommit();
    }

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
            'type' => 'group_join_request',
            'message' => $this->requester->name.' requested to join '.$this->group->name.'.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->requester->id,
            'actor_name' => $this->requester->name,
            'actor_username' => $this->requester->username,
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
        ];
    }

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
