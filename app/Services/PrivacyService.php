<?php

namespace App\Services;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PrivacyService
{
    public function __construct(private readonly FollowService $followService) {}

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
        if (! $viewer) {
            return $query
                ->whereHas('author', fn (Builder $author) => $author
                    ->where('is_private', false)
                    ->where('is_banned', false)
                )
                ->where('visibility', 'public');
        }

        return $query->where(function (Builder $visibility) use ($viewer): void {
            $visibility
                ->where('user_id', $viewer->id)
                ->orWhere(function (Builder $public) use ($viewer): void {
                    $public->where('visibility', 'public')
                        ->whereHas('author', function (Builder $author) use ($viewer): void {
                            $author
                                ->where('is_private', false)
                                ->where('is_banned', false);

                            if (User::hasBlocksTable()) {
                                $author->whereNotIn('id', $viewer->blocking()->pluck('users.id'));
                            }
                        });
                })
                ->orWhere(function (Builder $followers) use ($viewer): void {
                    $followers->whereIn('user_id', $viewer->acceptedFollowing()->pluck('users.id'))
                        ->whereNotIn('visibility', ['private']);
                });
        });
    }
}
