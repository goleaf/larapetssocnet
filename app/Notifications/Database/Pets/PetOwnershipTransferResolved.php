<?php

namespace App\Notifications\Database\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PetOwnershipTransferResolved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Pet $pet,
        public readonly string $status,
        public readonly ?User $respondingUser = null,
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
            'type' => 'pet_ownership_transfer_resolved',
            'message' => $this->message(),
            'route' => $this->resolveRoute(),
            'actor_id' => $this->respondingUser?->id,
            'actor_name' => $this->respondingUser?->name,
            'actor_username' => $this->respondingUser?->username,
            'pet_id' => $this->pet->id,
            'pet_name' => $this->pet->name,
            'pet_slug' => $this->pet->slug,
            'status' => $this->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }

    protected function message(): string
    {
        return match ($this->status) {
            'accepted' => ($this->respondingUser?->name ?? 'The co-owner').' accepted ownership of '.$this->pet->name.'.',
            'declined' => ($this->respondingUser?->name ?? 'The co-owner').' declined ownership of '.$this->pet->name.'.',
            'expired' => 'The ownership transfer for '.$this->pet->name.' expired.',
            default => 'The ownership transfer for '.$this->pet->name.' was updated.',
        };
    }

    protected function resolveRoute(): string
    {
        if (Route::has('pets.edit')) {
            return route('pets.edit', ['pet' => $this->pet]);
        }

        return route('pets.show', ['pet' => $this->pet]);
    }
}
