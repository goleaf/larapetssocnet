<?php

namespace App\Notifications;

use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

class NewReaction extends Notification
{
    use Queueable;

    /**
     * @var array<string, string>
     */
    private const REACTION_LABELS = [
        'love' => 'love',
        'cute' => 'cute',
        'funny' => 'funny',
        'wow' => 'wow',
        'sad' => 'sad',
        'support' => 'support',
    ];

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
            $reactionLabel = self::REACTION_LABELS[$this->reactionType] ?? $this->reactionType;

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
