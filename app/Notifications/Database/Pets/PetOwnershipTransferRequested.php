<?php

namespace App\Notifications\Database\Pets;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwnershipTransfer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PetOwnershipTransferRequested extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly Pet $pet,
        public readonly PetOwnershipTransfer $transfer,
        public readonly User $currentOwner,
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
            'expires_at' => $this->expiresAtIso(),
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

    private function expiresAtIso(): ?string
    {
        $expiresAt = $this->transfer->getAttribute('expires_at');

        if ($expiresAt instanceof CarbonInterface) {
            return $expiresAt->toISOString();
        }

        if (is_string($expiresAt) && $expiresAt !== '') {
            return CarbonImmutable::parse($expiresAt)->toISOString();
        }

        return null;
    }
}
