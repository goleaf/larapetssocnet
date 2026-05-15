<?php

namespace App\Http\Controllers\Social;

use App\Enums\FollowAbility;
use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowRequestController extends Controller
{
    public function __construct(private readonly FollowService $followService) {}

    public function index(Request $request): View
    {
        $this->authorize(FollowAbility::ManageRequests, $request->user());

        $requests = $request->user()
            ->pendingFollowRequests()
            ->with('media')
            ->withCount(['acceptedFollowers', 'posts'])
            ->latest('follows.created_at')
            ->paginate(20);

        return view('social.follow-requests.index', ['requests' => $requests]);
    }

    public function approve(Request $request, User $user): JsonResponse
    {
        $this->authorize(FollowAbility::ManageRequests, $request->user());

        if (! $request->user()->canApproveFollowRequestFrom($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to approve this request.',
            ], 403);
        }

        if (! $request->user()->pendingFollowRequests()->where('users.id', $user->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No pending request found.',
            ], 404);
        }

        $this->followService->approve($request->user(), $user);

        return response()->json([
            'success' => true,
            'message' => "Approved @{$user->username}.",
            'new_followers_count' => (int) $request->user()->fresh()->followers_count,
        ]);
    }

    public function reject(Request $request, User $user): JsonResponse
    {
        $this->authorize(FollowAbility::ManageRequests, $request->user());

        $this->followService->reject($request->user(), $user);

        return response()->json([
            'success' => true,
            'message' => "Rejected @{$user->username}.",
        ]);
    }

    public function approveAll(Request $request): JsonResponse
    {
        $this->authorize(FollowAbility::ManageRequests, $request->user());

        $count = $this->followService->approveAll($request->user());

        return response()->json([
            'success' => true,
            'approved_count' => $count,
            'message' => $count > 0 ? "Approved {$count} follow requests." : 'No pending requests.',
        ]);
    }
}
