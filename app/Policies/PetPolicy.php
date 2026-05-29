<?php

namespace App\Policies;

use App\Enums\Pets\PetOwnerRole;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwner;
use App\Models\Pets\PetOwnershipTransfer;
use App\Services\PetVisibilityService;
use Illuminate\Auth\Access\Response;

class PetPolicy
{
    public const ACTION_VIEW_PRIVATE_CONTENT = 'view-private-content';

    public const ACTION_CREATE_POST = 'create-post';

    public const ACTION_ADD_MILESTONE = 'add-milestone';

    public const ACTION_EDIT_PROFILE = 'edit-profile';

    public const ACTION_MANAGE_GALLERY = 'manage-gallery';

    public const ACTION_MANAGE_HEALTH = 'manage-health';

    public const ACTION_MANAGE_ADOPTION = 'manage-adoption';

    public const ACTION_MANAGE_CO_OWNERS = 'manage-co-owners';

    public const ACTION_ARCHIVE_PET = 'archive-pet';

    public const ACTION_DELETE_PET = 'delete-pet';

    public const ACTION_TRANSFER_OWNERSHIP = 'transfer-ownership';

    /**
     * @var array<string, list<string>>
     */
    public const ROLE_CAPABILITIES = [
        'owner' => [
            self::ACTION_VIEW_PRIVATE_CONTENT,
            self::ACTION_CREATE_POST,
            self::ACTION_ADD_MILESTONE,
            self::ACTION_EDIT_PROFILE,
            self::ACTION_MANAGE_GALLERY,
            self::ACTION_MANAGE_HEALTH,
            self::ACTION_MANAGE_ADOPTION,
            self::ACTION_MANAGE_CO_OWNERS,
            self::ACTION_ARCHIVE_PET,
            self::ACTION_DELETE_PET,
            self::ACTION_TRANSFER_OWNERSHIP,
        ],
        'admin' => [
            self::ACTION_VIEW_PRIVATE_CONTENT,
            self::ACTION_CREATE_POST,
            self::ACTION_ADD_MILESTONE,
            self::ACTION_EDIT_PROFILE,
            self::ACTION_MANAGE_GALLERY,
            self::ACTION_MANAGE_HEALTH,
            self::ACTION_MANAGE_ADOPTION,
            self::ACTION_MANAGE_CO_OWNERS,
            self::ACTION_ARCHIVE_PET,
        ],
        'poster' => [
            self::ACTION_VIEW_PRIVATE_CONTENT,
            self::ACTION_CREATE_POST,
            self::ACTION_ADD_MILESTONE,
        ],
        'viewer' => [
            self::ACTION_VIEW_PRIVATE_CONTENT,
        ],
    ];

    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Pet $pet): Response|bool
    {
        $visibility = app(PetVisibilityService::class);

        if ($visibility->hasBlockedRelationship($user, $pet)) {
            return Response::denyAsNotFound();
        }

        return $visibility->canViewPetShell($user, $pet);
    }

    public function viewShell(?User $user, Pet $pet): Response|bool
    {
        return $this->view($user, $pet);
    }

    public function viewPosts(?User $user, Pet $pet): bool
    {
        return app(PetVisibilityService::class)->canViewPetPosts($user, $pet);
    }

    public function viewGallery(?User $user, Pet $pet): bool
    {
        return app(PetVisibilityService::class)->canViewPetGallery($user, $pet);
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, Pet $pet): bool
    {
        return $this->canWrite($user, $pet, self::ACTION_EDIT_PROFILE);
    }

    public function manageAvatar(User $user, Pet $pet): bool
    {
        return $this->update($user, $pet);
    }

    public function manageGallery(User $user, Pet $pet): bool
    {
        return $this->canWrite($user, $pet, self::ACTION_MANAGE_GALLERY);
    }

    public function manageHealth(User $user, Pet $pet): bool
    {
        return $this->canWrite($user, $pet, self::ACTION_MANAGE_HEALTH);
    }

    public function manageAdoption(User $user, Pet $pet): bool
    {
        return $this->canWrite($user, $pet, self::ACTION_MANAGE_ADOPTION);
    }

    public function manageMilestones(User $user, Pet $pet): bool
    {
        return $this->canWrite($user, $pet, self::ACTION_ADD_MILESTONE);
    }

    public function manageOwners(User $user, Pet $pet): bool
    {
        return $this->canWrite($user, $pet, self::ACTION_MANAGE_CO_OWNERS);
    }

    public function delete(User $user, Pet $pet): bool
    {
        return $this->canAct($user, $pet, self::ACTION_DELETE_PET);
    }

    public function restore(User $user, Pet $pet): bool
    {
        return $this->update($user, $pet);
    }

    public function forceDelete(User $user, Pet $pet): bool
    {
        return $this->update($user, $pet);
    }

    public function viewFollowers(?User $user, Pet $pet): bool
    {
        return app(PetVisibilityService::class)->canViewPetFollowers($user, $pet);
    }

    public function createPostForPet(User $user, Pet $pet): bool
    {
        return $this->canWrite($user, $pet, self::ACTION_CREATE_POST);
    }

    public function attachPost(User $user, Pet $pet, Post $post): bool
    {
        if (! $this->createPostForPet($user, $pet)) {
            return false;
        }

        return (int) $post->user_id === (int) $user->getKey();
    }

    public function detachPost(User $user, Pet $pet, Post $post): bool
    {
        return $this->attachPost($user, $pet, $post);
    }

    public function attachToPost(User $user, Pet $pet, Post $post): bool
    {
        return $this->attachPost($user, $pet, $post);
    }

    public function detachFromPost(User $user, Pet $pet, Post $post): bool
    {
        return $this->detachPost($user, $pet, $post);
    }

    public function follow(User $user, Pet $pet): bool
    {
        if ($user->is($pet->owner)) {
            return false;
        }

        if ((bool) $user->is_banned) {
            return false;
        }

        if ((bool) $pet->getAttribute('is_archived')) {
            return false;
        }

        return app(PetVisibilityService::class)->canView($user, $pet);
    }

    public function unfollow(User $user, Pet $pet): bool
    {
        return ! $user->is($pet->owner);
    }

    public function archive(User $user, Pet $pet): bool
    {
        return $this->canAct($user, $pet, self::ACTION_ARCHIVE_PET);
    }

    public function transferOwnership(User $user, Pet $pet): bool
    {
        return $this->canWrite($user, $pet, self::ACTION_TRANSFER_OWNERSHIP);
    }

    public function viewPrivateContent(User $user, Pet $pet): bool
    {
        return $this->canAct($user, $pet, self::ACTION_VIEW_PRIVATE_CONTENT);
    }

    private function canWrite(User $user, Pet $pet, string $action): bool
    {
        if ((bool) $pet->getAttribute('is_archived')) {
            return false;
        }

        return $this->canAct($user, $pet, $action);
    }

    private function canAct(User $user, Pet $pet, string $action): bool
    {
        if ($user->hasAnyRole(['admin', 'moderator'])) {
            return true;
        }

        $role = $this->roleFor($user, $pet);

        if ($role instanceof PetOwnerRole) {
            return in_array($action, self::ROLE_CAPABILITIES[$role->value] ?? [], true);
        }

        return $this->legacyCoOwnerCan($user, $pet, $action);
    }

    private function roleFor(User $user, Pet $pet): ?PetOwnerRole
    {
        if ((int) $pet->user_id === (int) $user->getKey()) {
            return PetOwnerRole::Owner;
        }

        if (PetOwnershipTransfer::query()
            ->where('pet_id', $pet->getKey())
            ->where('current_owner_user_id', $user->getKey())
            ->where('status', PetOwnershipTransfer::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->exists()) {
            return PetOwnerRole::Owner;
        }

        $role = $pet->ownerships()
            ->where('user_id', $user->getKey())
            ->whereNotNull('accepted_at')
            ->value('role');

        if (! is_string($role)) {
            return null;
        }

        return PetOwnerRole::tryFrom($role);
    }

    private function legacyCoOwnerCan(User $user, Pet $pet, string $action): bool
    {
        $ownership = $pet->ownerships()
            ->where('user_id', $user->getKey())
            ->where('role', PetOwner::ROLE_CO_OWNER)
            ->whereNotNull('accepted_at')
            ->first();

        if (! $ownership instanceof PetOwner) {
            return false;
        }

        return match ($action) {
            self::ACTION_VIEW_PRIVATE_CONTENT => true,
            self::ACTION_CREATE_POST, self::ACTION_ADD_MILESTONE => (bool) $ownership->can_post,
            self::ACTION_EDIT_PROFILE => (bool) $ownership->can_edit,
            self::ACTION_MANAGE_GALLERY => (bool) $ownership->can_manage_gallery,
            self::ACTION_MANAGE_HEALTH => (bool) $ownership->can_manage_health,
            self::ACTION_MANAGE_ADOPTION => (bool) $ownership->can_manage_adoption,
            self::ACTION_DELETE_PET => (bool) $ownership->can_delete,
            default => false,
        };
    }
}
