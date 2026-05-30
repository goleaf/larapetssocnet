<?php

declare(strict_types=1);

namespace App\Notifications\Database\Contests;

use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class ContestWinner extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly Contest $contest,
        public readonly ContestEntry $entry,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'contest_winner',
            'message' => "🎉 Congratulations! Your entry won the \"{$this->contest->title}\" contest!",
            'contest_id' => $this->contest->id,
            'contest_title' => $this->contest->title,
            'entry_id' => $this->entry->id,
            'action_url' => "/contests/{$this->contest->slug}",
        ];
    }
}
