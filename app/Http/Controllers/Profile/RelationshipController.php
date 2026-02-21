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

        $actor->follow($user);

        return $this->successResponse('User followed.', [
            'is_following' => true,
            'followers_count' => $user->followers()->count(),
            'following_count' => $actor->following()->count(),
        ]);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return $this->errorResponse('You cannot unfollow yourself.', 422);
        }

        $actor->unfollow($user);

        return $this->successResponse('User unfollowed.', [
            'is_following' => false,
            'followers_count' => $user->followers()->count(),
            'following_count' => $actor->following()->count(),
        ]);
    }

    public function block(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return $this->errorResponse('You cannot block yourself.', 422);
        }

        $actor->block($user);
        $actor->unfollow($user);
        $user->unfollow($actor);

        return $this->successResponse('User blocked.', [
            'is_blocked' => true,
            'blocked_users_count' => $actor->blockedUsers()->count(),
        ]);
    }

    public function unblock(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($actor->id === $user->id) {
            return $this->errorResponse('You cannot unblock yourself.', 422);
        }

        $actor->unblock($user);

        return $this->successResponse('User unblocked.', [
            'is_blocked' => false,
            'blocked_users_count' => $actor->blockedUsers()->count(),
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
