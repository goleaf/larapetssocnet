<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RelationshipController extends Controller
{
    public function follow(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return $this->errorResponse('You cannot follow yourself.', 422);
        }

        if ($actor->hasBlocked($user) || $actor->isBlockedBy($user)) {
            return $this->errorResponse('Follow is unavailable because one user has blocked the other.', 422);
        }

        $followed = $actor->follow($user);

        if (! $followed) {
            return $this->errorResponse('Unable to follow this user.', 422, [
                'is_following' => $actor->isFollowing($user),
            ]);
        }

        return $this->successResponse('User followed.', [
            'is_following' => true,
            'followers_count' => $user->fresh()?->followers_count,
            'following_count' => $actor->fresh()?->following_count,
        ]);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return $this->errorResponse('You cannot unfollow yourself.', 422);
        }

        $unfollowed = $actor->unfollow($user);

        if (! $unfollowed) {
            return $this->errorResponse('Unable to unfollow this user.', 422, [
                'is_following' => $actor->isFollowing($user),
            ]);
        }

        return $this->successResponse('User unfollowed.', [
            'is_following' => false,
            'followers_count' => $user->fresh()?->followers_count,
            'following_count' => $actor->fresh()?->following_count,
        ]);
    }

    public function block(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return $this->errorResponse('You cannot block yourself.', 422);
        }

        $blocked = $actor->block($user);

        if (! $blocked) {
            return $this->errorResponse('Unable to block this user.', 422, [
                'is_blocked' => $actor->hasBlocked($user),
            ]);
        }

        return $this->successResponse('User blocked.', [
            'is_blocked' => true,
            'blocked_users_count' => $actor->fresh()?->blocked_users_count,
        ]);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return $this->errorResponse('You cannot unblock yourself.', 422);
        }

        $unblocked = $actor->unblock($user);

        if (! $unblocked) {
            return $this->errorResponse('Unable to unblock this user.', 422, [
                'is_blocked' => $actor->hasBlocked($user),
            ]);
        }

        return $this->successResponse('User unblocked.', [
            'is_blocked' => false,
            'blocked_users_count' => $actor->fresh()?->blocked_users_count,
        ]);
    }

    protected function successResponse(string $message, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function errorResponse(string $message, int $status = 400, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $status);
    }
}
