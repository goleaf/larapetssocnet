<?php

namespace App\Observers;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Services\CounterCacheService;

class CommentObserver
{
    /**
     * Handle the Comment "created" event.
     */
    public function created(Comment $comment): void
    {
        $post = Post::query()
            ->select(['id', 'comments_count'])
            ->whereKey($comment->post_id)
            ->first();

        if ($post) {
            app(CounterCacheService::class)->safeIncrement($post, 'comments_count');
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
            ->select(['id', 'comments_count'])
            ->whereKey($comment->post_id)
            ->first();

        if ($post) {
            app(CounterCacheService::class)->safeDecrement($post, 'comments_count');
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
            ->select(['id', 'comments_count'])
            ->whereKey($comment->post_id)
            ->first();

        if ($post) {
            app(CounterCacheService::class)->safeIncrement($post, 'comments_count');
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
}
