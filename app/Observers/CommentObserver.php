<?php

namespace App\Observers;

use App\Actions\Comments\FinalizeDeletedComment;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CommentThreadSubscriptionService;
use App\Services\CounterCacheService;
use Illuminate\Support\Facades\Cache;

class CommentObserver
{
    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        $post = Post::query()
            ->select(['id', 'user_id', 'comments_count'])
            ->whereKey($comment->post_id)
            ->first();

        if ($post) {
            app(CounterCacheService::class)->safeIncrement($post, 'comments_count');
            $this->incrementPostOwnerCommentsReceived($post);
            Cache::forget('posts:'.$post->getKey().':comment-insights');
        }

        if ($comment->parent_id !== null) {
            $parent = Comment::query()
                ->select(['id', 'replies_count'])
                ->whereKey($comment->parent_id)
                ->first();

            $parent?->incrementCounter('replies_count');
        }

        $author = User::query()
            ->select(['id'])
            ->whereKey($comment->user_id)
            ->first();

        if ($post instanceof Post && $author instanceof User) {
            app(CommentThreadSubscriptionService::class)->syncAuthorSubscription($author, $post, $comment);
        }
    }

    /**
     * Handle the Comment "updated" event.
     */
    public function updated(Comment $comment): void {}

    /**
     * Handle the Comment "deleted" event.
     */
    public function deleted(Comment $comment): void
    {
        if (method_exists($comment, 'isForceDeleting') && $comment->isForceDeleting()) {
            return;
        }

        FinalizeDeletedComment::dispatch((int) $comment->getKey())->afterCommit();
        Cache::forget('posts:'.$comment->post_id.':comment-insights');

        if ($comment->parent_id !== null) {
            $parent = Comment::query()
                ->select(['id', 'replies_count'])
                ->whereKey($comment->parent_id)
                ->first();

            $parent?->decrementCounter('replies_count');
        }
    }

    /**
     * Handle the Comment "restored" event.
     */
    public function restored(Comment $comment): void
    {
        $post = Post::query()
            ->select(['id', 'user_id', 'comments_count'])
            ->whereKey($comment->post_id)
            ->first();

        if ($post) {
            app(CounterCacheService::class)->safeIncrement($post, 'comments_count');
            $this->incrementPostOwnerCommentsReceived($post);
            Cache::forget('posts:'.$post->getKey().':comment-insights');
        }

        if ($comment->parent_id !== null) {
            $parent = Comment::query()
                ->select(['id', 'replies_count'])
                ->whereKey($comment->parent_id)
                ->first();

            $parent?->incrementCounter('replies_count');
        }
    }

    /**
     * Handle the Comment "force deleted" event.
     */
    public function forceDeleted(Comment $comment): void {}

    private function incrementPostOwnerCommentsReceived(Post $post): void
    {
        User::query()
            ->select(['id'])
            ->whereKey($post->getAttribute('user_id'))
            ->first()
            ?->incrementCounter('post_comments_received_count');
    }
}
