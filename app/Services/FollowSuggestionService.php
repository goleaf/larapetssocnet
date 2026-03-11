<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FollowSuggestionService
{
    /**
     * @return Collection<int, User>
     */
    public function forUser(User $viewer, int $limit = 4): Collection
    {
        $viewerId = (int) $viewer->getKey();
        $acceptedFollowing = $viewer->acceptedFollowing()->select('users.id');
        $pendingRequests = $viewer->sentPendingRequests()->select('users.id');
        $sharedGroupIds = $viewer->groups()->select('groups.id');

        $suggestions = User::query()
            ->active()
            ->notBlockedFor($viewer)
            ->whereKeyNot($viewerId)
            ->where('show_in_explore', true)
            ->where('is_private', false)
            ->whereNotIn('users.id', $acceptedFollowing)
            ->whereNotIn('users.id', $pendingRequests)
            ->withCount([
                'acceptedFollowers as mutual_followers_count' => function ($query) use ($acceptedFollowing): void {
                    $query->whereIn('users.id', $acceptedFollowing);
                },
                'groups as shared_groups_count' => function ($query) use ($sharedGroupIds): void {
                    $query->whereIn('groups.id', $sharedGroupIds);
                },
            ])
            ->orderByDesc('mutual_followers_count')
            ->orderByDesc('shared_groups_count')
            ->orderByDesc('followers_count')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->with('media')
            ->select([
                'users.id',
                'users.name',
                'users.username',
                'users.avatar_path',
                'users.profile_photo_path',
                'users.followers_count',
                'users.last_seen_at',
                'users.is_private',
            ])
            ->get();

        return $this->attachReasons($suggestions);
    }

    /**
     * @param  Collection<int, User>  $suggestions
     * @return Collection<int, User>
     */
    private function attachReasons(Collection $suggestions): Collection
    {
        foreach ($suggestions as $suggested) {
            $reason = null;
            $mutualCount = (int) ($suggested->mutual_followers_count ?? 0);
            $sharedGroups = (int) ($suggested->shared_groups_count ?? 0);

            if ($mutualCount > 0) {
                $reason = sprintf(
                    'Followed by %d %s you follow',
                    $mutualCount,
                    Str::plural('person', $mutualCount)
                );
            } elseif ($sharedGroups > 0) {
                $reason = sprintf(
                    'Shares %d %s with you',
                    $sharedGroups,
                    Str::plural('group', $sharedGroups)
                );
            }

            $suggested->setAttribute('suggestion_reason', $reason);
        }

        return $suggestions;
    }
}
