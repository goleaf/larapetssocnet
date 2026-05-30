<?php

namespace App\Notifications\Database\Posts;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class MentionedInPost extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $author,
        public readonly Post $post,
    ) {}

    /**
     * @return array<int, string>
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
            'type' => 'post_mention',
            'message' => $this->author->name.' mentioned you in a post.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->author->id,
            'actor_name' => $this->author->name,
            'actor_username' => $this->author->username,
            'post_id' => $this->post->id,
            'post_excerpt' => Str::limit((string) $this->post->body, 120),
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
        if (Route::has('posts.show')) {
            return route('posts.show', ['post' => $this->post]);
        }

        return url('/');
    }
}
