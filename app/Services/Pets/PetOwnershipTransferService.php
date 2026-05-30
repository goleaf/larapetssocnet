<?php

namespace App\Services\Pets;

use App\Enums\Pets\PetOwnerRole;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwner;
use App\Models\Pets\PetOwnershipTransfer;
use App\Notifications\Database\Pets\PetOwnershipTransferRequested;
use App\Notifications\Database\Pets\PetOwnershipTransferResolved;
use App\Services\PetOwnershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PetOwnershipTransferService
{
    public function __construct(
        private readonly PetOwnershipService $ownership,
    ) {}

    public function initiate(User $actor, Pet $pet, User $proposedOwner): PetOwnershipTransfer
    {
        return DB::transaction(function () use ($actor, $pet, $proposedOwner): PetOwnershipTransfer {
            if (! $actor->can('transferOwnership', $pet)) {
                throw new AuthorizationException('Only the current owner can transfer this pet.');
            }

            if ((int) $actor->getKey() === (int) $proposedOwner->getKey()) {
                throw $this->validation('Choose a different co-owner to receive ownership.');
            }

            $targetOwnership = $pet->ownerships()
                ->where('user_id', $proposedOwner->getKey())
                ->whereNotNull('accepted_at')
                ->first();

            if (! $targetOwnership instanceof PetOwner) {
                throw $this->validation('Ownership can only be transferred to an accepted co-owner.');
            }

            $transfer = PetOwnershipTransfer::query()->updateOrCreate(
                [
                    'pet_id' => $pet->getKey(),
                    'status' => PetOwnershipTransfer::STATUS_PENDING,
                ],
                [
                    'current_owner_user_id' => $pet->user_id,
                    'proposed_owner_user_id' => $proposedOwner->getKey(),
                    'expires_at' => now()->addDays(7),
                ],
            );

            $proposedOwner->notify(new PetOwnershipTransferRequested($pet, $transfer, $actor));

            return $transfer->fresh();
        });
    }

    public function accept(User $proposedOwner, Pet $pet, PetOwnershipTransfer $transfer): Pet
    {
        return DB::transaction(function () use ($proposedOwner, $pet, $transfer): Pet {
            $this->assertTransferBelongsToPet($pet, $transfer);

            if ((int) $transfer->proposed_owner_user_id !== (int) $proposedOwner->getKey()) {
                throw $this->validation('This transfer belongs to another user.');
            }

            if (! $transfer->isPending()) {
                throw $this->validation('This ownership transfer is no longer active.');
            }

            if ((bool) $pet->getAttribute('is_archived')) {
                throw $this->validation('Archived pet profiles are read-only.');
            }

            $currentOwnerId = (int) $transfer->current_owner_user_id;
            $newOwnerId = (int) $transfer->proposed_owner_user_id;

            PetOwner::query()
                ->where('pet_id', $pet->getKey())
                ->update(['is_primary_owner' => false]);

            PetOwner::query()->updateOrCreate(
                [
                    'pet_id' => $pet->getKey(),
                    'user_id' => $currentOwnerId,
                ],
                [
                    'role' => PetOwnerRole::Admin->value,
                    'is_primary_owner' => false,
                    ...$this->ownership->permissionsForRole(PetOwnerRole::Admin),
                    'accepted_at' => now(),
                ],
            );

            PetOwner::query()->updateOrCreate(
                [
                    'pet_id' => $pet->getKey(),
                    'user_id' => $newOwnerId,
                ],
                [
                    'role' => PetOwnerRole::Owner->value,
                    'is_primary_owner' => true,
                    ...$this->permissionsForOwner(),
                    'accepted_at' => now(),
                ],
            );

            $pet->forceFill(['user_id' => $newOwnerId])->save();

            $transfer->forceFill([
                'status' => PetOwnershipTransfer::STATUS_ACCEPTED,
            ])->save();

            User::query()
                ->whereKey($currentOwnerId)
                ->first()
                ?->notify(new PetOwnershipTransferResolved($pet->fresh(), 'accepted', $proposedOwner));

            return $pet->fresh();
        });
    }

    public function decline(User $proposedOwner, Pet $pet, PetOwnershipTransfer $transfer): void
    {
        DB::transaction(function () use ($proposedOwner, $pet, $transfer): void {
            $this->assertTransferBelongsToPet($pet, $transfer);

            if ((int) $transfer->proposed_owner_user_id !== (int) $proposedOwner->getKey()) {
                throw $this->validation('This transfer belongs to another user.');
            }

            if ($transfer->status !== PetOwnershipTransfer::STATUS_PENDING) {
                return;
            }

            $currentOwnerId = (int) $transfer->current_owner_user_id;
            $transfer->delete();

            User::query()
                ->whereKey($currentOwnerId)
                ->first()
                ?->notify(new PetOwnershipTransferResolved($pet, 'declined', $proposedOwner));
        });
    }

    public function cancel(User $actor, Pet $pet, PetOwnershipTransfer $transfer): void
    {
        DB::transaction(function () use ($actor, $pet, $transfer): void {
            $this->assertTransferBelongsToPet($pet, $transfer);

            if (! $actor->can('transferOwnership', $pet)) {
                throw new AuthorizationException('Only the current owner can cancel this transfer.');
            }

            if ($transfer->status === PetOwnershipTransfer::STATUS_PENDING) {
                $transfer->delete();
            }
        });
    }

    public function expirePending(): int
    {
        $expired = PetOwnershipTransfer::query()
            ->with(['pet', 'currentOwner', 'proposedOwner'])
            ->where('status', PetOwnershipTransfer::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->get();

        $expired->each(function (PetOwnershipTransfer $transfer): void {
            $pet = $transfer->pet;
            $proposedOwner = $transfer->proposedOwner;

            $transfer->currentOwner?->notify(new PetOwnershipTransferResolved(
                $pet,
                'expired',
                $proposedOwner,
            ));

            $transfer->delete();
        });

        return $expired->count();
    }

    /**
     * @return array<string, bool>
     */
    private function permissionsForOwner(): array
    {
        return [
            'can_post' => true,
            'can_edit' => true,
            'can_manage_health' => true,
            'can_manage_gallery' => true,
            'can_manage_adoption' => true,
            'can_delete' => true,
        ];
    }

    private function assertTransferBelongsToPet(Pet $pet, PetOwnershipTransfer $transfer): void
    {
        if ((int) $transfer->pet_id !== (int) $pet->getKey()) {
            throw $this->validation('This transfer belongs to another pet.');
        }
    }

    private function validation(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'pet_ownership_transfer' => $message,
        ]);
    }
}
