<?php

namespace App\Notifications;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class MentionedInComment extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $commenter,
        public readonly Post $post,
        public readonly Comment $comment,
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
            'type' => 'comment_mention',
            'message' => $this->commenter->name.' mentioned you in a comment.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->commenter->id,
            'actor_name' => $this->commenter->name,
            'actor_username' => $this->commenter->username,
            'post_id' => $this->post->id,
            'post_excerpt' => Str::limit((string) $this->post->body, 120),
            'comment_id' => $this->comment->id,
            'comment_excerpt' => Str::limit((string) $this->comment->body, 120),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('posts.show')) {
            return route('posts.show', ['post' => $this->post]).'#comment-'.$this->comment->id;
        }

        return url('/');
    }
}
