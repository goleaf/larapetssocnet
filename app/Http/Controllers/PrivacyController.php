<?php

namespace App\Http\Controllers;

use App\Services\PrivacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public function __construct(private readonly PrivacyService $privacyService) {}

    public function toggle(Request $request): JsonResponse
    {
        $result = $this->privacyService->togglePrivacy($request->user());

        return response()->json([
            'success' => true,
            'is_private' => $result['is_private'],
            'message' => $result['message'],
            'auto_approved' => $result['pending_requests_auto_approved'],
        ]);
    }
}
