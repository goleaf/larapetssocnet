<?php

namespace App\Http\Controllers;

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
            $this->authorize('unfollow', $user);
            $this->followService->unfollow($actor, $user);

            return back()->with('success', "Unfollowed @{$user->username}.");
        }

        $this->authorize('follow', $user);
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
        $this->authorize('follow', $user);

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
        $this->authorize('unfollow', $user);

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
        $this->authorize('viewFollowers', $user);

        $followers = $user->acceptedFollowers()
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

        return view('profile.followers', compact('user', 'followers'));
    }

    public function following(Request $request, User $user): View
    {
        $this->authorize('viewFollowing', $user);

        $following = $user->acceptedFollowing()
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

        return view('profile.following', compact('user', 'following'));
    }

    public function removeFollower(Request $request, User $user): JsonResponse
    {
        $owner = $request->user();

        if ($owner->is($user)) {
            abort(422, 'Cannot remove yourself.');
        }

        $this->followService->removeFollower($owner, $user);

        return response()->json([
            'success' => true,
            'message' => "@{$user->username} has been removed from your followers.",
        ]);
    }
}
