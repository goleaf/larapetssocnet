<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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

        if (method_exists($pet, 'followers')) {
            $pet->followers()->syncWithoutDetaching([$user->getAuthIdentifier()]);
        } else {
            $this->fallbackFollow((int) $pet->getKey(), (int) $user->getAuthIdentifier());
        }

        return response()->json([
            'followed' => true,
            'followers_count' => $this->followersCount($pet),
        ]);
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401);

        $pet = $this->resolvePet($slug);

        if (method_exists($pet, 'followers')) {
            $pet->followers()->detach($user->getAuthIdentifier());
        } else {
            $this->fallbackUnfollow((int) $pet->getKey(), (int) $user->getAuthIdentifier());
        }

        return response()->json([
            'followed' => false,
            'followers_count' => $this->followersCount($pet),
        ]);
    }

    protected function resolvePet(string $slug): Pet
    {
        return Pet::query()
            ->where('slug', $slug)
            ->orWhereKey($slug)
            ->firstOrFail();
    }

    protected function isOwner(Pet $pet, int $userId): bool
    {
        $ownerId = data_get($pet, 'user_id') ?? data_get($pet, 'owner_id');

        return (int) $ownerId === $userId;
    }

    protected function followersCount(Pet $pet): int
    {
        if (method_exists($pet, 'followers')) {
            return (int) $pet->followers()->count();
        }

        if (! $this->hasPetUserPivot()) {
            return 0;
        }

        return (int) DB::table('pet_user')->where('pet_id', $pet->getKey())->count();
    }

    protected function fallbackFollow(int $petId, int $userId): void
    {
        if (! $this->hasPetUserPivot()) {
            return;
        }

        DB::table('pet_user')->updateOrInsert([
            'pet_id' => $petId,
            'user_id' => $userId,
        ]);
    }

    protected function fallbackUnfollow(int $petId, int $userId): void
    {
        if (! $this->hasPetUserPivot()) {
            return;
        }

        DB::table('pet_user')
            ->where('pet_id', $petId)
            ->where('user_id', $userId)
            ->delete();
    }

    protected function hasPetUserPivot(): bool
    {
        try {
            return Schema::hasTable('pet_user');
        } catch (Throwable) {
            return false;
        }
    }
}
