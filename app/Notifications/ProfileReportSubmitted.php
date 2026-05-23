<?php

namespace App\Notifications;

use App\Models\Identity\User;
use App\Models\Moderation\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProfileReportSubmitted extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Report $report,
        public readonly User $reporter,
        public readonly User $reportedUser,
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
            'type' => 'profile_report_submitted',
            'message' => '@'.$this->reportedUser->username.' was reported for '.Report::profileReasonLabel($this->report->reason).'.',
            'report_id' => $this->report->getKey(),
            'reporter_user_id' => $this->reporter->getKey(),
            'reporter_username' => $this->reporter->username,
            'reported_user_id' => $this->reportedUser->getKey(),
            'reported_username' => $this->reportedUser->username,
            'reason' => $this->report->reason,
            'reason_label' => Report::profileReasonLabel($this->report->reason),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->toArray($notifiable);
    }
}
