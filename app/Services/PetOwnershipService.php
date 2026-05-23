<?php

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwner;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PetOwnershipService
{
    /**
     * @param  array<string, bool>  $permissions
     */
    public function addCoOwner(Pet $pet, User $inviter, User $coOwner, array $permissions = []): PetOwner
    {
        if ((int) $pet->user_id === (int) $coOwner->getKey()) {
            throw new InvalidArgumentException('The primary owner is already assigned to this pet.');
        }

        return DB::transaction(function () use ($pet, $inviter, $coOwner, $permissions): PetOwner {
            $ownership = PetOwner::query()->updateOrCreate([
                'pet_id' => $pet->getKey(),
                'user_id' => $coOwner->getKey(),
            ], [
                'invited_by_user_id' => $inviter->getKey(),
                'role' => PetOwner::ROLE_CO_OWNER,
                ...$this->normalizePermissions($permissions),
                'accepted_at' => now(),
            ]);

            return $ownership->refresh();
        });
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, bool>
     */
    private function normalizePermissions(array $permissions): array
    {
        $columns = [
            'can_post',
            'can_edit',
            'can_manage_health',
            'can_manage_gallery',
            'can_manage_adoption',
            'can_delete',
        ];

        $normalized = [];

        foreach ($columns as $column) {
            $normalized[$column] = (bool) ($permissions[$column] ?? false);
        }

        return $normalized;
    }
}
