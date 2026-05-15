<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pets\Pet;
use App\Services\PetFollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetFollowController extends Controller
{
    public function store(Request $request, Pet $pet, PetFollowService $petFollowService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $this->authorize('follow', $pet);

        $followed = $petFollowService->follow($user, $pet);

        return response()->json([
            'followed' => $followed || $pet->isFollowedBy($user),
            'followers_count' => (int) $pet->fresh()->followers_count,
        ]);
    }

    public function destroy(Request $request, Pet $pet, PetFollowService $petFollowService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $this->authorize('unfollow', $pet);

        $petFollowService->unfollow($user, $pet);

        return response()->json([
            'followed' => false,
            'followers_count' => (int) $pet->fresh()->followers_count,
        ]);
    }
}
