<?php

namespace App\Notifications\Database\Messaging;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Models\Identity\User;
use App\Models\Messaging\Message;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NewMessage extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly User $sender,
        public readonly Message $message,
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
            'type' => 'new_message',
            'message' => $this->sender->name.' sent you a new message.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->sender->id,
            'actor_name' => $this->sender->name,
            'actor_username' => $this->sender->username,
            'message_id' => $this->message->id,
            'message_preview' => Str::limit((string) $this->message->body, 120),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('messages.index')) {
            return route('messages.index');
        }

        if (Route::has('profile.show')) {
            return route('profile.show', ['user' => $this->sender]);
        }

        return url('/');
    }
}
