<?php

declare(strict_types=1);

namespace App\Notifications\Database\Groups;

use App\Models\Groups\Group;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class GroupDigestReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Group $group,
        public readonly int $postCount,
        public readonly CarbonInterface $windowStart,
        public readonly CarbonInterface $windowEnd,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
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
        $plural = $this->postCount === 1 ? 'post' : 'posts';

        return [
            'type' => 'group_digest',
            'message' => "{$this->group->name} has {$this->postCount} new {$plural}.",
            'route' => $this->resolveRoute(),
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'post_count' => $this->postCount,
            'window_start' => $this->windowStart->toIso8601String(),
            'window_end' => $this->windowEnd->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('groups.show')) {
            return route('groups.show', ['group' => $this->group]);
        }

        return route('groups.index');
    }
}
