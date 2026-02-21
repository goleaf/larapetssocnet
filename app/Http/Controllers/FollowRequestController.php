<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowRequestController extends Controller
{
    public function __construct(private readonly FollowService $followService) {}

    public function index(Request $request): View
    {
        $requests = $request->user()
            ->pendingFollowRequests()
            ->with('media')
            ->withCount(['acceptedFollowers', 'posts'])
            ->latest('follows.created_at')
            ->paginate(20);

        return view('follow-requests.index', compact('requests'));
    }

    public function approve(Request $request, User $user): JsonResponse
    {
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
        $this->followService->reject($request->user(), $user);

        return response()->json([
            'success' => true,
            'message' => "Rejected @{$user->username}.",
        ]);
    }

    public function approveAll(Request $request): JsonResponse
    {
        $count = $this->followService->approveAll($request->user());

        return response()->json([
            'success' => true,
            'approved_count' => $count,
            'message' => $count > 0 ? "Approved {$count} follow requests." : 'No pending requests.',
        ]);
    }
}
