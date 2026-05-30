<?php

declare(strict_types=1);

namespace App\Notifications\Database\Account;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UsernameChanged extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly string $oldUsername,
        public readonly string $newUsername
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
        return [
            'type' => 'username_changed',
            'old_username' => $this->oldUsername,
            'new_username' => $this->newUsername,
            'message' => "Your username was changed to @{$this->newUsername}.",
            'action_url' => route('profile.show', $this->newUsername),
        ];
    }
}
