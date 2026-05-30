<?php

namespace App\Notifications\Database\Pets;

use App\Models\Pets\Pet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PetBirthdayToday extends Notification
{
    use Queueable;

    public function __construct(public readonly Pet $pet) {}

    /**
     * @return array<int, string>
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
            'type' => 'pet_birthday_today',
            'message' => $this->pet->name.' has a birthday today.',
            'route' => $this->resolveRoute(),
            'pet_id' => $this->pet->getKey(),
            'pet_name' => $this->pet->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    private function resolveRoute(): string
    {
        if (Route::has('pets.show')) {
            return route('pets.show', ['pet' => $this->pet]);
        }

        return url('/');
    }
}
