<?php

namespace App\Notifications\Database\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnershipTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PetOwnershipTransferRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Pet $pet,
        public readonly PetOwnershipTransfer $transfer,
        public readonly User $currentOwner,
    ) {
        $this->afterCommit();
    }

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
            'type' => 'pet_ownership_transfer_requested',
            'message' => $this->currentOwner->name.' asked you to become the primary owner of '.$this->pet->name.'.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->currentOwner->id,
            'actor_name' => $this->currentOwner->name,
            'actor_username' => $this->currentOwner->username,
            'pet_id' => $this->pet->id,
            'pet_name' => $this->pet->name,
            'pet_slug' => $this->pet->slug,
            'transfer_id' => $this->transfer->id,
            'expires_at' => $this->transfer->expires_at?->toISOString(),
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
