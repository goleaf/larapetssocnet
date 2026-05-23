<?php

namespace App\Http\Controllers\Social;

use App\Exceptions\CannotBlockAdminException;
use App\Exceptions\CannotBlockSelfException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Social\BlockUserRequest;
use App\Models\Identity\User;
use App\Services\BlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlockController extends Controller
{
    public function __construct(private readonly BlockService $blockService) {}

    public function block(BlockUserRequest $request, User $user): JsonResponse|RedirectResponse
    {
        try {
            $this->blockService->block($request->user(), $user);
        } catch (CannotBlockSelfException $exception) {
            if (! $request->expectsJson()) {
                return back()->with('error', $exception->getMessage());
            }

            return $this->errorResponse($exception->getMessage(), 422);
        } catch (CannotBlockAdminException $exception) {
            if (! $request->expectsJson()) {
                return back()->with('error', $exception->getMessage());
            }

            return $this->errorResponse($exception->getMessage(), 403);
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->route('feed.index')
                ->with('success', 'You have blocked this user.');
        }

        return $this->successResponse("@{$user->username} has been blocked.", [
            'is_blocked' => true,
            'blocked_users_count' => $request->user()->fresh()?->blocked_users_count,
            'follow_status' => 'none',
            'followers_count' => $user->fresh()?->followers_count,
        ]);
    }

    public function unblock(BlockUserRequest $request, User $user): JsonResponse
    {
        $this->blockService->unblock($request->user(), $user);

        return $this->successResponse("@{$user->username} has been unblocked.", [
            'is_blocked' => false,
            'blocked_users_count' => $request->user()->fresh()?->blocked_users_count,
            'follow_status' => 'none',
            'followers_count' => $user->fresh()?->followers_count,
        ]);
    }

    public function index(Request $request): View
    {
        $blocked = $this->blockService->getBlockedUsers($request->user());

        return view('settings.blocked-users', ['blocked' => $blocked]);
    }

    protected function successResponse(string $message, array $data = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    protected function errorResponse(string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [],
        ], $status);
    }
}
