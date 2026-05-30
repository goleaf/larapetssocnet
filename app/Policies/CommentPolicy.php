<?php

namespace App\Policies;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;

class CommentPolicy
{
    public function view(?User $user, Comment $comment): bool
    {
        $post = $this->postFor($comment);

        return $post instanceof Post && app(PostPolicy::class)->view($user, $post);
    }

    public function create(User $user, Post $post): bool
    {
        if ($post->belongsToArchivedGroup()) {
            return false;
        }

        return app(PostPolicy::class)->view($user, $post);
    }

    public function reply(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        if ($this->belongsToArchivedGroup($comment)) {
            return false;
        }

        $post = $this->postFor($comment);

        if (! $post instanceof Post || ! app(PostPolicy::class)->view($user, $post)) {
            return false;
        }

        $commentAuthor = $this->authorFor($comment);

        return $commentAuthor instanceof User && ! $user->hasBlockingRelationshipWith($commentAuthor);
    }

    public function update(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        if ((int) $comment->user_id !== (int) $user->getKey()) {
            return false;
        }

        return $comment->created_at === null || $comment->created_at->greaterThanOrEqualTo(now()->subHour());
    }

    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        return (int) $comment->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function react(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        if ($this->belongsToArchivedGroup($comment)) {
            return false;
        }

        $post = $this->postFor($comment);

        if (! $post instanceof Post || ! app(PostPolicy::class)->view($user, $post)) {
            return false;
        }

        $commentAuthor = $this->authorFor($comment);

        return $commentAuthor instanceof User && ! $user->hasBlockingRelationshipWith($commentAuthor);
    }

    public function report(User $user, Comment $comment): bool
    {
        if ((int) $comment->user_id === (int) $user->getKey()) {
            return false;
        }

        $post = $this->postFor($comment);

        return $post instanceof Post && app(PostPolicy::class)->view($user, $post);
    }

    public function pin(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        $post = $this->postFor($comment);

        return $post instanceof Post
            && (int) $post->user_id === (int) $user->getKey()
            && app(PostPolicy::class)->view($user, $post);
    }

    private function belongsToArchivedGroup(Comment $comment): bool
    {
        $post = $this->postFor($comment);

        return $post instanceof Post && $post->belongsToArchivedGroup();
    }

    private function postFor(Comment $comment): ?Post
    {
        if ($comment->relationLoaded('post')) {
            $post = $comment->getRelation('post');

            return $post instanceof Post ? $post : null;
        }

        return Post::query()
            ->whereKey($comment->post_id)
            ->first();
    }

    private function authorFor(Comment $comment): ?User
    {
        if ($comment->relationLoaded('user')) {
            $author = $comment->getRelation('user');

            return $author instanceof User ? $author : null;
        }

        return User::query()
            ->whereKey($comment->user_id)
            ->first();
    }
}
