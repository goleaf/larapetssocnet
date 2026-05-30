<?php

declare(strict_types=1);

namespace App\Notifications\Database\Moderation;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class ReportThresholdReached extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly Model $reportable,
        public readonly int $pendingCount,
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
            'type' => 'report_threshold_reached',
            'message' => 'Pending reports reached '.$this->pendingCount.'.',
            'reportable_type' => $this->reportable->getMorphClass(),
            'reportable_id' => $this->reportable->getKey(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
