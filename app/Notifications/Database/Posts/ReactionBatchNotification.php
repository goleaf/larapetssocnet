<?php

namespace App\Notifications\Database\Posts;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class ReactionBatchNotification extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly User $leadReactor,
        public readonly Post $post,
        public readonly int $reactionCount,
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
            'type' => 'reaction_digest',
            'message' => $this->formatMessage(),
            'route' => $this->resolveRoute(),
            'actor_id' => $this->leadReactor->id,
            'actor_name' => $this->leadReactor->name,
            'actor_username' => $this->leadReactor->username,
            'post_id' => $this->post->id,
            'reaction_count' => $this->reactionCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    private function formatMessage(): string
    {
        $otherCount = max(0, $this->reactionCount - 1);

        if ($otherCount === 0) {
            return $this->leadReactor->name.' reacted to your post.';
        }

        return $this->leadReactor->name.' and '.$otherCount.' '.str('other')->plural($otherCount).' reacted to your post.';
    }

    private function resolveRoute(): string
    {
        if (Route::has('posts.show')) {
            return route('posts.show', ['post' => $this->post]);
        }

        return url('/');
    }
}
