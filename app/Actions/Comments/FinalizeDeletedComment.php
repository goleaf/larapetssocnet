<?php

namespace App\Actions\Comments;

use App\Enums\Support\Queue\QueueName;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CounterCacheService;
use App\Support\Queue\HasDefaultQueueRuntime;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeDeletedComment implements ShouldBeUnique, ShouldQueue
{
    use HasDefaultQueueRuntime;
    use Queueable;

    public function __construct(public readonly int $commentId)
    {
        $this->onQueue(QueueName::Comments->routingName());
    }

    public function uniqueId(): string
    {
        return (string) $this->commentId;
    }

    public function handle(CounterCacheService $counters): void
    {
        $comment = Comment::withTrashed()
            ->select(['id', 'post_id', 'deleted_at'])
            ->whereKey($this->commentId)
            ->first();

        if (! $comment instanceof Comment || ! $comment->trashed()) {
            return;
        }

        $post = Post::query()
            ->select(['id', 'user_id', 'comments_count'])
            ->whereKey($comment->post_id)
            ->first();

        if (! $post instanceof Post) {
            return;
        }

        $counters->safeDecrement($post, 'comments_count');

        User::query()
            ->select(['id'])
            ->whereKey($post->getAttribute('user_id'))
            ->first()
            ?->decrementCounter('post_comments_received_count');
    }
}
