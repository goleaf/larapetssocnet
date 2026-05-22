<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Social\Follow;
use Illuminate\Database\Eloquent\Builder;

class PrivacyService
{
    public function togglePrivacy(User $user): array
    {
        if (! $user->is_private) {
            $user->makePrivate();

            return [
                'is_private' => true,
                'message' => 'Your account is now private. Only approved followers can see your posts.',
                'pending_requests_auto_approved' => 0,
            ];
        }

        $pendingCount = Follow::query()
            ->where('following_id', $user->getKey())
            ->where('status', 'pending')
            ->count();

        $user->makePublic();

        $suffix = $pendingCount > 0
            ? " {$pendingCount} pending follow request(s) have been automatically approved."
            : '';

        return [
            'is_private' => false,
            'message' => 'Your account is now public.'.$suffix,
            'pending_requests_auto_approved' => $pendingCount,
        ];
    }

    public function canUserViewContent(User $owner, ?User $viewer): bool
    {
        return $owner->canViewPosts($viewer);
    }

    public function filterPostsForViewer(Builder $query, ?User $viewer): Builder
    {
        if (! $viewer instanceof User) {
            return $query
                ->whereHas('author', fn (Builder $author) => $author
                    ->where('is_private', false)
                    ->where('is_banned', false)
                )
                ->where('visibility', Post::VISIBILITY_PUBLIC);
        }

        $followingIdsQuery = static fn () => Follow::query()
            ->select('follows.following_id')
            ->where('follows.follower_id', $viewer->getKey())
            ->where('follows.status', 'accepted');

        $mutualFollowingIdsQuery = static fn () => Follow::query()
            ->from('follows as viewer_follows')
            ->select('viewer_follows.following_id')
            ->where('viewer_follows.follower_id', $viewer->getKey())
            ->where('viewer_follows.status', 'accepted')
            ->whereIn('viewer_follows.following_id', Follow::query()
                ->from('follows as author_follows')
                ->select('author_follows.follower_id')
                ->where('author_follows.following_id', $viewer->getKey())
                ->where('author_follows.status', 'accepted'));

        return $query->where(function (Builder $visibility) use ($viewer, $followingIdsQuery, $mutualFollowingIdsQuery): void {
            $visibility
                ->where('user_id', $viewer->id)
                ->orWhere(function (Builder $public) use ($viewer): void {
                    $public->where('visibility', Post::VISIBILITY_PUBLIC)
                        ->whereHas('author', function (Builder $author) use ($viewer): void {
                            $author
                                ->where('is_private', false)
                                ->where('is_banned', false);

                            if (User::hasBlocksTable()) {
                                $author
                                    ->whereNotIn('id', $viewer->blocking()->select('users.id'))
                                    ->whereNotIn('id', $viewer->blockedBy()->select('users.id'));
                            }
                        });
                })
                ->orWhere(function (Builder $followers) use ($followingIdsQuery): void {
                    $followers->whereIn('user_id', $followingIdsQuery())
                        ->whereIn('visibility', [Post::VISIBILITY_PUBLIC, Post::VISIBILITY_FOLLOWERS]);
                })
                ->orWhere(function (Builder $friends) use ($mutualFollowingIdsQuery): void {
                    $friends->whereIn('user_id', $mutualFollowingIdsQuery())
                        ->where('visibility', Post::VISIBILITY_FRIENDS);
                });
        });
    }
}
