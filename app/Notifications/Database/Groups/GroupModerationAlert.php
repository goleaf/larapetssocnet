<?php

declare(strict_types=1);

namespace App\Notifications\Database\Groups;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class GroupModerationAlert extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly Group $group,
        public readonly Post $post,
        public readonly User $moderator,
        public readonly string $action,
    ) {}

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
        return [
            'type' => 'group_moderation_alert',
            'message' => $this->moderator->name.' '.$this->action.' your post in '.$this->group->name.'.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->moderator->id,
            'actor_name' => $this->moderator->name,
            'actor_username' => $this->moderator->username,
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'post_id' => $this->post->id,
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
