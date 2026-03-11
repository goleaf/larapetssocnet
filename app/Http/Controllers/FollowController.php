<?php

namespace App\Http\Controllers;

use App\Enums\FollowAbility;
use App\Models\Follow;
use App\Models\User;
use App\Services\FollowService;
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
        $this->authorize(FollowAbility::ViewFollowers, $user);

        $viewer = $request->user();

        $followers = $user->acceptedFollowers()
            ->active()
            ->notBlockedFor($viewer)
            ->with('media')
            ->withCount(['acceptedFollowers as followers_count', 'posts'])
            ->when($request->string('q')->toString(), function ($query, $term): void {
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('username', 'like', "%{$term}%");
                });
            })
            ->paginate(24)
            ->withQueryString();

        $followStatusMap = $viewer
            ? $this->followService->followStatusMap($viewer, $followers->getCollection())
            : [];

        return view('profile.followers', compact('user', 'followers', 'followStatusMap'));
    }

    public function following(Request $request, User $user): View
    {
        $this->authorize(FollowAbility::ViewFollowing, $user);

        $viewer = $request->user();

        $following = $user->acceptedFollowing()
            ->active()
            ->notBlockedFor($viewer)
            ->with('media')
            ->withCount(['acceptedFollowers as followers_count', 'posts'])
            ->when($request->string('q')->toString(), function ($query, $term): void {
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('username', 'like', "%{$term}%");
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

        return view('profile.following', compact('user', 'following', 'followsYouIds', 'followStatusMap'));
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
