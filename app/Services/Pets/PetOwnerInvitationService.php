<?php

namespace App\Services\Pets;

use App\Enums\Pets\PetOwnerRole;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwner;
use App\Models\Pets\PetOwnerInvitation;
use App\Notifications\PetOwnerInvitationReceived;
use App\Services\PetOwnershipService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PetOwnerInvitationService
{
    public function __construct(
        private readonly PetOwnershipService $ownership,
    ) {}

    public function invite(User $actor, Pet $pet, User $invitee, PetOwnerRole|string $role): PetOwnerInvitation
    {
        return DB::transaction(function () use ($actor, $pet, $invitee, $role): PetOwnerInvitation {
            if (! $actor->can('manageOwners', $pet)) {
                throw new AuthorizationException('You are not allowed to invite co-owners for this pet.');
            }

            $role = $this->resolveInviteRole($role);

            if ((int) $invitee->getKey() === (int) $pet->user_id) {
                throw $this->validation('The primary owner already manages this pet.');
            }

            if ($pet->ownerships()
                ->where('user_id', $invitee->getKey())
                ->whereNotNull('accepted_at')
                ->exists()) {
                throw $this->validation('This user already manages this pet.');
            }

            $invitation = PetOwnerInvitation::query()->updateOrCreate(
                [
                    'pet_id' => $pet->getKey(),
                    'invited_user_id' => $invitee->getKey(),
                    'status' => PetOwnerInvitation::STATUS_PENDING,
                ],
                [
                    'inviting_user_id' => $actor->getKey(),
                    'role' => $role->value,
                    'expires_at' => now()->addDays(14),
                    'responded_at' => null,
                ],
            );

            $invitee->notify(new PetOwnerInvitationReceived($pet, $invitation, $actor));

            return $invitation->fresh();
        });
    }

    public function accept(User $invitee, Pet $pet, PetOwnerInvitation $invitation): PetOwner
    {
        return DB::transaction(function () use ($invitee, $pet, $invitation): PetOwner {
            $this->assertInvitationBelongsToPet($pet, $invitation);

            if ((int) $invitation->invited_user_id !== (int) $invitee->getKey()) {
                throw $this->validation('This invitation belongs to another user.');
            }

            if (! $invitation->isPending()) {
                throw $this->validation('This invitation is no longer active.');
            }

            if ((bool) $pet->getAttribute('is_archived')) {
                throw $this->validation('Archived pet profiles are read-only.');
            }

            $role = $invitation->roleValue();

            $ownership = PetOwner::query()->updateOrCreate(
                [
                    'pet_id' => $pet->getKey(),
                    'user_id' => $invitee->getKey(),
                ],
                [
                    'invited_by_user_id' => $invitation->inviting_user_id,
                    'role' => $role->value,
                    'is_primary_owner' => false,
                    ...$this->ownership->permissionsForRole($role),
                    'accepted_at' => now(),
                ],
            );

            $invitation->forceFill([
                'status' => PetOwnerInvitation::STATUS_ACCEPTED,
                'responded_at' => now(),
            ])->save();

            return $ownership->fresh();
        });
    }

    public function decline(User $invitee, Pet $pet, PetOwnerInvitation $invitation): PetOwnerInvitation
    {
        return DB::transaction(function () use ($invitee, $pet, $invitation): PetOwnerInvitation {
            $this->assertInvitationBelongsToPet($pet, $invitation);

            if ((int) $invitation->invited_user_id !== (int) $invitee->getKey()) {
                throw $this->validation('This invitation belongs to another user.');
            }

            if ($invitation->status !== PetOwnerInvitation::STATUS_PENDING) {
                return $invitation;
            }

            $invitation->forceFill([
                'status' => PetOwnerInvitation::STATUS_DECLINED,
                'responded_at' => now(),
            ])->save();

            return $invitation->fresh();
        });
    }

    public function expirePending(): int
    {
        return PetOwnerInvitation::query()
            ->where('status', PetOwnerInvitation::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => PetOwnerInvitation::STATUS_EXPIRED,
                'responded_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function resolveInviteRole(PetOwnerRole|string $role): PetOwnerRole
    {
        $role = $role instanceof PetOwnerRole ? $role : PetOwnerRole::tryFrom($role);

        if (! $role instanceof PetOwnerRole || $role === PetOwnerRole::Owner) {
            throw $this->validation('Choose Admin, Poster, or Viewer for a co-owner invitation.');
        }

        return $role;
    }

    private function assertInvitationBelongsToPet(Pet $pet, PetOwnerInvitation $invitation): void
    {
        if ((int) $invitation->pet_id !== (int) $pet->getKey()) {
            throw $this->validation('This invitation belongs to another pet.');
        }
    }

    private function validation(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'pet_owner_invitation' => $message,
        ]);
    }
}
