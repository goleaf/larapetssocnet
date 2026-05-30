<?php

namespace App\Notifications\Database\Pets;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Models\Pets\Pet;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PetBirthdayCoOwnerNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly Pet $pet,
        public readonly int $age,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
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
        return $this->toDatabase($notifiable);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'pet_birthday_co_owner',
            'message' => "{$this->pet->name} turns {$this->age} today. Their co-owner family can celebrate together.",
            'route' => Route::has('pets.show') ? route('pets.show', $this->pet) : url('/'),
            'pet_id' => $this->pet->getKey(),
            'pet_name' => $this->pet->name,
            'age' => $this->age,
        ];
    }
}
