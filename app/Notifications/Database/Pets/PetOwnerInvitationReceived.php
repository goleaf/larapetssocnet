<?php

namespace App\Notifications\Database\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnerInvitation;
use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PetOwnerInvitationReceived extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly Pet $pet,
        public readonly PetOwnerInvitation $invitation,
        public readonly User $inviter,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'pet_owner_invitation',
            'message' => $this->inviter->name.' invited you to help manage '.$this->pet->name.'.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->inviter->id,
            'actor_name' => $this->inviter->name,
            'actor_username' => $this->inviter->username,
            'pet_id' => $this->pet->id,
            'pet_name' => $this->pet->name,
            'pet_slug' => $this->pet->slug,
            'invitation_id' => $this->invitation->id,
            'role' => $this->invitation->roleValue()->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('pets.edit')) {
            return route('pets.edit', ['pet' => $this->pet]);
        }

        return route('pets.show', ['pet' => $this->pet]);
    }
}
