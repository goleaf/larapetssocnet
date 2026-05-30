<?php

namespace App\Notifications\Database\Events;

use App\Models\Activities\Event as EventModel;
use App\Models\Identity\User;
use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class EventAttendee extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly User $attendee,
        public readonly EventModel $event,
        public readonly string $status = EventModel::ATTENDEE_GOING,
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
            'type' => 'event_attendee',
            'message' => $this->formatMessage(),
            'route' => $this->resolveRoute(),
            'actor_id' => $this->attendee->id,
            'actor_name' => $this->attendee->name,
            'actor_username' => $this->attendee->username,
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'status' => $this->status,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function formatMessage(): string
    {
        return match ($this->status) {
            EventModel::ATTENDEE_DECLINED => $this->attendee->name.' declined your event "'.$this->event->title.'".',
            EventModel::ATTENDEE_INTERESTED => $this->attendee->name.' is interested in your event "'.$this->event->title.'".',
            default => $this->attendee->name.' is attending your event "'.$this->event->title.'".',
        };
    }

    protected function resolveRoute(): string
    {
        if (Route::has('events.show')) {
            return route('events.show', ['event' => $this->event]);
        }

        if (Route::has('explore.index')) {
            return route('explore.index');
        }

        return url('/');
    }
}
