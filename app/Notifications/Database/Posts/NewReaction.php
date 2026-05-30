<?php

namespace App\Notifications\Database\Posts;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class NewReaction extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly User $reactor,
        public readonly Post $post,
        public readonly ?string $reactionType = null,
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
            'type' => 'new_reaction',
            'message' => $this->formatMessage(),
            'route' => $this->resolveRoute(),
            'actor_id' => $this->reactor->id,
            'actor_name' => $this->reactor->name,
            'actor_username' => $this->reactor->username,
            'post_id' => $this->post->id,
            'reaction_type' => $this->reactionType,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function formatMessage(): string
    {
        if (filled($this->reactionType)) {
            $labels = Reaction::labelMap();
            $reactionLabel = strtolower($labels[Reaction::normalizeType((string) $this->reactionType)] ?? (string) $this->reactionType);

            return $this->reactor->name.' reacted ('.$reactionLabel.') to your post.';
        }

        return $this->reactor->name.' reacted to your post.';
    }

    protected function resolveRoute(): string
    {
        if (Route::has('posts.show')) {
            return route('posts.show', ['post' => $this->post]);
        }

        return url('/');
    }
}
