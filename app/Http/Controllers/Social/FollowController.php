<?php

namespace App\Http\Controllers\Social;

use App\Enums\FollowAbility;
use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Models\Social\Follow;
use App\Services\FollowService;
use App\Support\Search\SearchInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowController extends Controller
{
    public function __construct(private readonly FollowService $followService) {}

    public function toggle(Request $request, User $user): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return $this->follow($request, $user);
        }

        $actor = $request->user();

        if ($actor->isFollowing($user) || $actor->hasRequestedFollow($user)) {
            $this->authorize(FollowAbility::Unfollow, $user);
            $this->followService->unfollow($actor, $user);

            return back()->with('success', "Unfollowed @{$user->username}.");
        }

        $this->authorize(FollowAbility::Follow, $user);
        $status = $this->followService->follow($actor, $user);

        return back()->with(
            'success',
            $status === 'pending'
                ? "Follow request sent to @{$user->username}."
                : "You are now following @{$user->username}."
        );
    }

    public function follow(Request $request, User $user): JsonResponse
    {
        $this->authorize(FollowAbility::Follow, $user);

        $status = $this->followService->follow($request->user(), $user);
        $followerCount = (int) $user->fresh()->followers_count;
        $isFollowing = $status === 'following';

        return response()->json([
            'success' => true,
            'follow_status' => $status,
            'follower_count' => $followerCount,
            'data' => [
                'is_following' => $isFollowing,
                'followers_count' => $followerCount,
            ],
            'message' => $status === 'pending'
                ? "Follow request sent to @{$user->username}."
                : "You are now following @{$user->username}.",
        ]);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $this->authorize(FollowAbility::Unfollow, $user);

        $this->followService->unfollow($request->user(), $user);
        $followerCount = (int) $user->fresh()->followers_count;

        return response()->json([
            'success' => true,
            'follow_status' => 'none',
            'follower_count' => $followerCount,
            'data' => [
                'is_following' => false,
                'followers_count' => $followerCount,
            ],
            'message' => "Unfollowed @{$user->username}.",
        ]);
    }

    public function followers(Request $request, User $user): View
    {
        $viewer = $request->user();
        $showMutualOnly = $request->boolean('mutual') && $viewer instanceof User && ! $viewer->is($user);

        if ($viewer && $viewer->hasBlockingRelationshipWith($user)) {
            abort(404);
        }

        $this->authorize(FollowAbility::ViewFollowers, $user);

        $search = SearchInput::normalize($request->string('q')->toString());
        $pattern = SearchInput::containsPattern($search);

        $followers = $user->acceptedFollowers()
            ->active()
            ->notBlockedFor($viewer)
            ->with('media')
            ->withCount(['acceptedFollowers as followers_count', 'posts'])
            ->when($showMutualOnly, function ($query) use ($viewer): void {
                $query->whereIn(
                    'users.id',
                    Follow::query()
                        ->select('following_id')
                        ->where('follower_id', $viewer->getKey())
                        ->where('status', 'accepted')
                );
            })
            ->when(SearchInput::hasSearchableLength($search), function ($query) use ($pattern): void {
                $query->where(function ($subQuery) use ($pattern): void {
                    $subQuery
                        ->where('name', 'like', $pattern)
                        ->orWhere('username', 'like', $pattern);
                });
            })
            ->paginate(24)
            ->withQueryString();

        $followStatusMap = $viewer
            ? $this->followService->followStatusMap($viewer, $followers->getCollection())
            : [];

        return view('profile.followers', [
            'user' => $user,
            'followers' => $followers,
            'followStatusMap' => $followStatusMap,
            'showMutualOnly' => $showMutualOnly,
            'q' => $search,
        ]);
    }

    public function following(Request $request, User $user): View
    {
        $viewer = $request->user();

        if ($viewer && $viewer->hasBlockingRelationshipWith($user)) {
            abort(404);
        }

        $this->authorize(FollowAbility::ViewFollowing, $user);

        $search = SearchInput::normalize($request->string('q')->toString());
        $pattern = SearchInput::containsPattern($search);

        $following = $user->acceptedFollowing()
            ->active()
            ->notBlockedFor($viewer)
            ->with('media')
            ->withCount(['acceptedFollowers as followers_count', 'posts'])
            ->when(SearchInput::hasSearchableLength($search), function ($query) use ($pattern): void {
                $query->where(function ($subQuery) use ($pattern): void {
                    $subQuery
                        ->where('name', 'like', $pattern)
                        ->orWhere('username', 'like', $pattern);
                });
            })
            ->paginate(24)
            ->withQueryString();

        $followsYouIds = [];
        $followingIds = $following->getCollection()->modelKeys();

        if ($followingIds !== []) {
            $followsYouIds = Follow::query()
                ->whereIn('follower_id', $followingIds)
                ->where('following_id', $user->getKey())
                ->where('status', 'accepted')
                ->pluck('follower_id')
                ->all();
        }

        $followStatusMap = $viewer
            ? $this->followService->followStatusMap($viewer, $following->getCollection())
            : [];

        return view('profile.following', ['user' => $user, 'following' => $following, 'followsYouIds' => $followsYouIds, 'followStatusMap' => $followStatusMap, 'q' => $search]);
    }

    public function removeFollower(Request $request, User $user): JsonResponse
    {
        $owner = $request->user();

        if ($owner->is($user)) {
            abort(422, 'Cannot remove yourself.');
        }

        $this->authorize(FollowAbility::RemoveFollower, $user);

        $this->followService->removeFollower($owner, $user);
        $followerCount = (int) $owner->fresh()->followers_count;

        return response()->json([
            'success' => true,
            'follow_status' => $owner->getFollowStatus($user),
            'follower_count' => $followerCount,
            'message' => "@{$user->username} has been removed from your followers.",
        ]);
    }
}
