<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

class CommentPolicy
{
    public function view(?User $user, Comment $comment): bool
    {
        return app(PostPolicy::class)->view($user, $comment->post);
    }

    public function create(User $user, Comment $comment): bool
    {
        return app(PostPolicy::class)->view($user, $comment->post);
    }

    public function update(User $user, Comment $comment): bool
    {
        return (int) $comment->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->update($user, $comment);
    }
}
