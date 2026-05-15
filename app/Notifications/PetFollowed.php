<?php

namespace App\Notifications;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class PetFollowed extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $follower,
        public readonly Pet $pet,
    ) {}

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
            'type' => 'pet_followed',
            'message' => $this->follower->name.' followed '.$this->pet->name.'.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->follower->id,
            'actor_name' => $this->follower->name,
            'actor_username' => $this->follower->username,
            'pet_id' => $this->pet->id,
            'pet_name' => $this->pet->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('pets.show')) {
            return route('pets.show', ['pet' => $this->pet]);
        }

        if (Route::has('profile.show')) {
            return route('profile.show', ['user' => $this->follower]);
        }

        return url('/');
    }
}
