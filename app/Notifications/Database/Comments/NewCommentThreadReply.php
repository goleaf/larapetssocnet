<?php

namespace App\Notifications\Database\Comments;

use App\Notifications\Database\QueuesDatabaseNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NewCommentThreadReply extends Notification implements ShouldQueue, ShouldQueueAfterCommit
{
    use QueuesDatabaseNotification;

    public function __construct(
        public readonly User $commenter,
        public readonly Post $post,
        public readonly Comment $rootComment,
        public readonly Comment $reply,
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
            'type' => 'comment_thread_reply',
            'message' => $this->commenter->name.' added a new reply in a thread you follow.',
            'route' => $this->resolveRoute(),
            'actor_id' => $this->commenter->id,
            'actor_name' => $this->commenter->name,
            'actor_username' => $this->commenter->username,
            'post_id' => $this->post->id,
            'post_excerpt' => Str::limit((string) $this->post->body, 120),
            'root_comment_id' => $this->rootComment->id,
            'comment_id' => $this->reply->id,
            'comment_excerpt' => Str::limit((string) $this->reply->body, 120),
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
            return route('posts.show', ['post' => $this->post]).'#comment-'.$this->reply->id;
        }

        return url('/');
    }
}
