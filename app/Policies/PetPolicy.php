<?php

namespace App\Policies;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetVisibilityService;

class PetPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Pet $pet): bool
    {
        return app(PetVisibilityService::class)->canViewPetShell($user, $pet);
    }

    public function viewShell(?User $user, Pet $pet): bool
    {
        return app(PetVisibilityService::class)->canViewPetShell($user, $pet);
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
        return $this->ownsOrModerates($user, $pet) || $pet->coOwnerCan($user, 'edit');
    }

    public function manageAvatar(User $user, Pet $pet): bool
    {
        return $this->update($user, $pet);
    }

    public function manageGallery(User $user, Pet $pet): bool
    {
        return $this->ownsOrModerates($user, $pet) || $pet->coOwnerCan($user, 'gallery');
    }

    public function manageHealth(User $user, Pet $pet): bool
    {
        return $this->ownsOrModerates($user, $pet) || $pet->coOwnerCan($user, 'health');
    }

    public function manageAdoption(User $user, Pet $pet): bool
    {
        return $this->ownsOrModerates($user, $pet) || $pet->coOwnerCan($user, 'adoption');
    }

    public function manageMilestones(User $user, Pet $pet): bool
    {
        return $this->ownsOrModerates($user, $pet) || $pet->coOwnerCan($user, 'post');
    }

    public function manageOwners(User $user, Pet $pet): bool
    {
        return $this->ownsOrModerates($user, $pet);
    }

    public function delete(User $user, Pet $pet): bool
    {
        return $this->ownsOrModerates($user, $pet) || $pet->coOwnerCan($user, 'delete');
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
        return $this->ownsOrModerates($user, $pet) || $pet->coOwnerCan($user, 'post');
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

        return app(PetVisibilityService::class)->canView($user, $pet);
    }

    public function unfollow(User $user, Pet $pet): bool
    {
        return ! $user->is($pet->owner);
    }

    private function ownsOrModerates(User $user, Pet $pet): bool
    {
        return (int) $pet->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }
}
