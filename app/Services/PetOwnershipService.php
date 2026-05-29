<?php

namespace App\Services;

use App\Enums\Pets\PetOwnerRole;
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
                'role' => $this->resolveRole($permissions)->value,
                'is_primary_owner' => false,
                ...$this->permissionsForRole($this->resolveRole($permissions)),
                'accepted_at' => now(),
            ]);

            return $ownership->refresh();
        });
    }

    /**
     * @param  array<string, bool|string>  $permissions
     */
    private function resolveRole(array $permissions): PetOwnerRole
    {
        $role = is_string($permissions['role'] ?? null)
            ? PetOwnerRole::tryFrom((string) $permissions['role'])
            : null;

        if ($role instanceof PetOwnerRole && $role !== PetOwnerRole::Owner) {
            return $role;
        }

        if ((bool) ($permissions['can_edit'] ?? false)
            || (bool) ($permissions['can_manage_health'] ?? false)
            || (bool) ($permissions['can_manage_gallery'] ?? false)
            || (bool) ($permissions['can_manage_adoption'] ?? false)
            || (bool) ($permissions['can_delete'] ?? false)) {
            return PetOwnerRole::Admin;
        }

        if ((bool) ($permissions['can_post'] ?? false)) {
            return PetOwnerRole::Poster;
        }

        return PetOwnerRole::Viewer;
    }

    /**
     * @return array<string, bool>
     */
    public function permissionsForRole(PetOwnerRole $role): array
    {
        return match ($role) {
            PetOwnerRole::Admin => [
                'can_post' => true,
                'can_edit' => true,
                'can_manage_health' => true,
                'can_manage_gallery' => true,
                'can_manage_adoption' => true,
                'can_delete' => false,
            ],
            PetOwnerRole::Poster => [
                'can_post' => true,
                'can_edit' => false,
                'can_manage_health' => false,
                'can_manage_gallery' => false,
                'can_manage_adoption' => false,
                'can_delete' => false,
            ],
            default => [
                'can_post' => false,
                'can_edit' => false,
                'can_manage_health' => false,
                'can_manage_gallery' => false,
                'can_manage_adoption' => false,
                'can_delete' => false,
            ],
        };
    }
}
