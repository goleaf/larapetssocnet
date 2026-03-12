<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NewCommentReply extends Notification
{
    use Queueable;

    public function __construct(
        public readonly User $commenter,
        public readonly Post $post,
        public readonly Comment $parent,
        public readonly Comment $reply,
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
            'type' => 'comment_reply',
            'message' => $this->commenter->name.' replied to your comment.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->commenter->id,
            'actor_name' => $this->commenter->name,
            'actor_username' => $this->commenter->username,
            'post_id' => $this->post->id,
            'post_excerpt' => Str::limit((string) $this->post->body, 120),
            'comment_id' => $this->reply->id,
            'comment_excerpt' => Str::limit((string) $this->reply->body, 120),
            'parent_comment_id' => $this->parent->id,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function resolveRoute(): string
    {
        if (Route::has('posts.show')) {
            return route('posts.show', ['post' => $this->post]).'#comment-'.$this->reply->id;
        }

        return url('/');
    }
}
