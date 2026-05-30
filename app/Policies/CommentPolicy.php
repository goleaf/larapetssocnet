<?php

namespace App\Policies;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;

class CommentPolicy
{
    public function view(?User $user, Comment $comment): bool
    {
        return $this->canViewPostFor($user, $comment);
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

        if ($this->belongsToArchivedGroup($comment, $user)) {
            return false;
        }

        if (! $this->canViewPostFor($user, $comment)) {
            return false;
        }

        return ! $this->hasBlockingRelationshipWithAuthor($user, $comment);
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

        if ($this->belongsToArchivedGroup($comment, $user)) {
            return false;
        }

        if (! $this->canViewPostFor($user, $comment)) {
            return false;
        }

        return ! $this->hasBlockingRelationshipWithAuthor($user, $comment);
    }

    public function report(User $user, Comment $comment): bool
    {
        if ((int) $comment->user_id === (int) $user->getKey()) {
            return false;
        }

        return $this->canViewPostFor($user, $comment);
    }

    public function pin(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        $post = $this->postFor($comment);

        return $post instanceof Post
            && (int) $post->user_id === (int) $user->getKey()
            && $this->canViewPostFor($user, $comment);
    }

    private function belongsToArchivedGroup(Comment $comment, User $user): bool
    {
        $preloaded = $this->preloadedBool($comment, $user, 'policy_post_belongs_to_archived_group');

        if ($preloaded !== null) {
            return $preloaded;
        }

        $post = $this->postFor($comment);

        return $post instanceof Post && $post->belongsToArchivedGroup();
    }

    private function canViewPostFor(?User $user, Comment $comment): bool
    {
        $preloaded = $this->preloadedBool($comment, $user, 'policy_can_view_post');

        if ($preloaded !== null) {
            return $preloaded;
        }

        $post = $this->postFor($comment);

        return $post instanceof Post && app(PostPolicy::class)->view($user, $post);
    }

    private function hasBlockingRelationshipWithAuthor(User $user, Comment $comment): bool
    {
        $preloaded = $this->preloadedBool($comment, $user, 'policy_author_blocked_by_viewer');

        if ($preloaded !== null) {
            return $preloaded;
        }

        $commentAuthor = $this->authorFor($comment);

        return ! $commentAuthor instanceof User || $user->hasBlockingRelationshipWith($commentAuthor);
    }

    private function preloadedBool(Comment $comment, ?User $user, string $attribute): ?bool
    {
        $viewerId = (int) ($user?->getKey() ?? 0);
        $preloadedViewerId = $comment->getAttribute('policy_viewer_id');

        if (! is_int($preloadedViewerId) || $preloadedViewerId !== $viewerId) {
            return null;
        }

        $value = $comment->getAttribute($attribute);

        return is_bool($value) ? $value : null;
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
