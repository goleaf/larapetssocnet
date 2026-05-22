<?php

namespace App\Observers;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CounterCacheService;

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

        $post = Post::query()
            ->select(['id', 'user_id', 'comments_count'])
            ->whereKey($comment->post_id)
            ->first();

        if ($post) {
            app(CounterCacheService::class)->safeDecrement($post, 'comments_count');
            $this->decrementPostOwnerCommentsReceived($post);
        }

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

    private function decrementPostOwnerCommentsReceived(Post $post): void
    {
        User::query()
            ->select(['id'])
            ->whereKey($post->getAttribute('user_id'))
            ->first()
            ?->decrementCounter('post_comments_received_count');
    }
}
