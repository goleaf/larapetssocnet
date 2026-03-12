<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

class VisibilityService
{
    public function canViewPost(?User $viewer, Post $post): bool
    {
        return $this->canView($viewer, $post);
    }

    public function canView(?User $viewer, Post $post): bool
    {
        $post->loadMissing('author');

        if ((bool) $post->author->is_banned) {
            return false;
        }

        if ($viewer?->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        if ($viewer?->is($post->author)) {
            return true;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($post->author)) {
            return false;
        }

        if (! $this->isPublishedForViewer($post)) {
            return false;
        }

        $accountPrivate = (bool) $post->author->is_private;
        $isFollower = $viewer ? $viewer->isFollowing($post->author) : false;

        return match ($post->visibility) {
            Post::VISIBILITY_PUBLIC => ! $accountPrivate || $isFollower,
            Post::VISIBILITY_FOLLOWERS => $isFollower,
            Post::VISIBILITY_PRIVATE => false,
            default => false,
        };
    }

    public function canViewOnProfile(?User $viewer, Post $post): bool
    {
        if ($viewer?->id === $post->user_id) {
            return true;
        }

        return $this->canView($viewer, $post);
    }

    public function canViewPostInFeed(?User $viewer, Post $post): bool
    {
        return $this->canView($viewer, $post);
    }

    public function canManagePost(User $actor, Post $post): bool
    {
        return $actor->id === $post->user_id || $actor->hasAnyRole(['admin', 'moderator']);
    }

    private function isPublishedForViewer(Post $post): bool
    {
        if ($post->status !== PostStatus::Published) {
            return false;
        }

        if (! $post->published_at) {
            return true;
        }

        return $post->published_at->isPast();
    }

    public function getVisibilityLabel(string $visibility): string
    {
        return match ($visibility) {
            Post::VISIBILITY_PUBLIC => 'Public',
            Post::VISIBILITY_FOLLOWERS => 'Followers',
            Post::VISIBILITY_PRIVATE => 'Only me',
            default => 'Public',
        };
    }

    public function getVisibilityIcon(string $visibility): string
    {
        return match ($visibility) {
            Post::VISIBILITY_PUBLIC => '🌍',
            Post::VISIBILITY_FOLLOWERS => '👥',
            Post::VISIBILITY_PRIVATE => '🔒',
            default => '🌍',
        };
    }

    public function shouldWarnOnDowngrade(Post $post, string $newVisibility): bool
    {
        $order = [
            Post::VISIBILITY_PUBLIC => 0,
            Post::VISIBILITY_FOLLOWERS => 1,
            Post::VISIBILITY_PRIVATE => 2,
        ];

        $current = $order[$post->visibility] ?? 0;
        $new = $order[$newVisibility] ?? 0;
        $hasEngagement = $post->likes_count > 0 || $post->comments_count > 0;

        return $new > $current && $hasEngagement;
    }
}
