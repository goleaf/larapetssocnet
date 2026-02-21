<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetFollowController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $pet = $this->resolvePet($slug);

        if ($this->isOwner($pet, (int) $user->getAuthIdentifier())) {
            return response()->json([
                'message' => 'Owners cannot follow their own pets.',
            ], 422);
        }

        $user->followPet($pet);

        return response()->json([
            'followed' => true,
            'followers_count' => (int) $pet->fresh()->followers_count,
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $pet = $this->resolvePet($slug);

        $user->unfollowPet($pet);

        return response()->json([
            'followed' => false,
            'followers_count' => (int) $pet->fresh()->followers_count,
        ]);
    }

    protected function resolvePet(string $slug): Pet
    {
        return Pet::query()
            ->where('slug', $slug)
            ->orWhere('id', $slug)
            ->firstOrFail();
    }

    protected function isOwner(Pet $pet, int $userId): bool
    {
        $ownerId = data_get($pet, 'user_id') ?? data_get($pet, 'owner_id');

        return (int) $ownerId === $userId;
    }
}
