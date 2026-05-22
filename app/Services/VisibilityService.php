<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;

class VisibilityService
{
    public function canViewPost(?User $viewer, Post $post): bool
    {
        return $this->canView($viewer, $post);
    }

    public function canView(?User $viewer, Post $post): bool
    {
        $post->loadMissing(['author', 'pet']);

        $author = $post->author;

        if (! $author instanceof User) {
            return false;
        }

        if ($author->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer?->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        if ($viewer?->is($author)) {
            return true;
        }

        $pet = $post->pet;

        if ($pet instanceof Pet && ! app(PetVisibilityService::class)->canViewPetPosts($viewer, $pet)) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($author)) {
            return false;
        }

        if (! $this->isPublishedForViewer($post)) {
            return false;
        }

        $accountPrivate = (bool) $author->is_private;
        $isFollower = $viewer && $viewer->isFollowing($author);
        $isFriend = $isFollower && $author->isFollowing($viewer);

        return match ($post->visibility) {
            Post::VISIBILITY_PUBLIC => ! $accountPrivate || $isFollower,
            Post::VISIBILITY_FOLLOWERS => $isFollower,
            Post::VISIBILITY_FRIENDS => $isFriend,
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
        if (! $post->status->isPubliclyReachable()) {
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
            Post::VISIBILITY_FRIENDS => 'Friends',
            Post::VISIBILITY_PRIVATE => 'Only me',
            default => 'Public',
        };
    }

    public function getVisibilityIcon(string $visibility): string
    {
        return match ($visibility) {
            Post::VISIBILITY_PUBLIC => '🌍',
            Post::VISIBILITY_FOLLOWERS => '👥',
            Post::VISIBILITY_FRIENDS => '🤝',
            Post::VISIBILITY_PRIVATE => '🔒',
            default => '🌍',
        };
    }

    public function shouldWarnOnDowngrade(Post $post, string $newVisibility): bool
    {
        $order = [
            Post::VISIBILITY_PUBLIC => 0,
            Post::VISIBILITY_FOLLOWERS => 1,
            Post::VISIBILITY_FRIENDS => 2,
            Post::VISIBILITY_PRIVATE => 3,
        ];

        $current = $order[$post->visibility] ?? 0;
        $new = $order[$newVisibility] ?? 0;
        $hasEngagement = $post->likes_count > 0 || $post->comments_count > 0;

        return $new > $current && $hasEngagement;
    }
}
