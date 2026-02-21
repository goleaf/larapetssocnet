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
        $pendingRequests = $request->user()
            ->pendingFollowRequests()
            ->with('media')
            ->paginate(24);

        return view('follow-requests.index', compact('pendingRequests'));
    }

    public function approve(Request $request, User $user): JsonResponse
    {
        $this->followService->approve($request->user(), $user);

        return response()->json([
            'success' => true,
            'message' => "Approved @{$user->username}.",
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
