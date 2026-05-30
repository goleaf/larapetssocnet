<?php

declare(strict_types=1);

namespace App\Notifications\Database\Achievements;

use App\Models\Gamification\Badge;
use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

class BadgeAwarded extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly Badge $badge,
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
            'type' => 'badge_awarded',
            'message' => "You earned the \"{$this->badge->name}\" badge! {$this->badge->icon}",
            'badge_id' => $this->badge->id,
            'badge_name' => $this->badge->name,
            'badge_icon' => $this->badge->icon,
            'action_url' => route('profile.show', $notifiable->username ?? $notifiable->id),
        ];
    }
}
