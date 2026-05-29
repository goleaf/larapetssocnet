<?php

namespace App\Notifications;

use App\Models\Pets\Pet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PetBirthdayFollowerNotification extends Notification
{
    use Queueable;

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
            'type' => 'pet_birthday_follower',
            'message' => "{$this->pet->name} turns {$this->age} today! Wish them a happy birthday",
            'route' => Route::has('pets.show') ? route('pets.show', $this->pet) : url('/'),
            'pet_id' => $this->pet->getKey(),
            'pet_name' => $this->pet->name,
            'age' => $this->age,
        ];
    }
}
