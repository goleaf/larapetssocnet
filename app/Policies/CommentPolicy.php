<?php

namespace App\Policies;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;

class CommentPolicy
{
    public function view(?User $user, Comment $comment): bool
    {
        return app(PostPolicy::class)->view($user, $comment->post);
    }

    public function create(User $user, Post $post): bool
    {
        return app(PostPolicy::class)->view($user, $post);
    }

    public function reply(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        if (! app(PostPolicy::class)->view($user, $comment->post)) {
            return false;
        }

        return ! $user->hasBlockingRelationshipWith($comment->user);
    }

    public function update(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        return (int) $comment->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }

    public function react(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        if (! app(PostPolicy::class)->view($user, $comment->post)) {
            return false;
        }

        return ! $user->hasBlockingRelationshipWith($comment->user);
    }

    public function report(User $user, Comment $comment): bool
    {
        if ((int) $comment->user_id === (int) $user->getKey()) {
            return false;
        }

        return app(PostPolicy::class)->view($user, $comment->post);
    }
}
