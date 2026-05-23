<?php

namespace App\Notifications;

use App\Models\Groups\Group;
use App\Models\Groups\GroupInvitation;
use App\Models\Identity\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class GroupInvitationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Group $group,
        public readonly GroupInvitation $invitation,
        public readonly User $inviter,
    ) {
        $this->afterCommit();
    }

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
            'type' => 'group_invitation',
            'message' => $this->inviter->name.' invited you to join '.$this->group->name.'.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->inviter->id,
            'actor_name' => $this->inviter->name,
            'actor_username' => $this->inviter->username,
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'invitation_id' => $this->invitation->id,
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

        return route('groups.index');
    }
}
