<?php

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\Block;
use App\Models\Social\Follow;
use Illuminate\Database\Eloquent\Builder;

class PetVisibilityService
{
    public function canView(?User $viewer, Pet $pet): bool
    {
        return $this->canViewPetShell($viewer, $pet);
    }

    public function canViewPetShell(?User $viewer, Pet $pet): bool
    {
        $pet->loadMissing('owner');

        $owner = $pet->owner;
        if (! $owner instanceof User) {
            return false;
        }

        if ($owner->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        if ($viewer instanceof User && $pet->isCoOwnedBy($viewer)) {
            return true;
        }

        $visibility = $this->petVisibility($pet);

        if ($visibility === 'private') {
            return false;
        }

        return true;
    }

    public function canViewPetPosts(?User $viewer, Pet $pet): bool
    {
        return $this->canViewFullProfile($viewer, $pet);
    }

    public function canViewPetGallery(?User $viewer, Pet $pet): bool
    {
        return $this->canViewFullProfile($viewer, $pet);
    }

    public function canViewPetFollowers(?User $viewer, Pet $pet): bool
    {
        return $this->canViewFollowers($viewer, $pet);
    }

    public function canViewFollowers(?User $viewer, Pet $pet): bool
    {
        if (! $this->canViewPetShell($viewer, $pet)) {
            return false;
        }

        if (! $viewer instanceof User) {
            return false;
        }

        return $viewer->is($pet->owner) || $viewer->hasAnyRole(['admin', 'moderator']);
    }

    public function canViewPetsForOwner(?User $viewer, User $owner): bool
    {
        if ($owner->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        if (! $owner->canViewProfile($viewer)) {
            return false;
        }

        return $this->petsVisibilityAllows($viewer, $owner);
    }

    /**
     * @param  Builder<Pet>  $query
     * @return Builder<Pet>
     */
    public function applyVisibleScope(Builder $query, ?User $viewer): Builder
    {
        $query->select(['pets.*']);

        $viewerId = (int) ($viewer?->getKey() ?? 0);
        $isAdmin = $viewer?->hasAnyRole(['admin', 'moderator']) ?? false;

        if ($viewer instanceof User && $viewer->isUnavailableForProfile()) {
            return $query->whereKey(-1);
        }

        $query->whereHas('owner', function (Builder $ownerQuery) use ($viewerId, $isAdmin): void {
            User::applyAvailableForProfiles($ownerQuery);

            if ($viewerId > 0 && User::hasBlocksTable()) {
                $ownerQuery
                    ->whereNotIn('users.id', Block::query()
                        ->select('blocks.blocker_id')
                        ->where('blocks.blocked_id', $viewerId))
                    ->whereNotIn('users.id', Block::query()
                        ->select('blocks.blocked_id')
                        ->where('blocks.blocker_id', $viewerId));
            }

            if ($isAdmin) {
                return;
            }

            if ($viewerId > 0) {
                $ownerQuery->where(function (Builder $visibilityQuery) use ($viewerId): void {
                    $visibilityQuery
                        ->where('users.id', $viewerId)
                        ->orWhere(function (Builder $privacyQuery) use ($viewerId): void {
                            $privacyQuery
                                ->where('is_private', false)
                                ->orWhere(function (Builder $followerQuery) use ($viewerId): void {
                                    $followerQuery
                                        ->where('is_private', true)
                                        ->whereIn('users.id', Follow::query()
                                            ->select('following_id')
                                            ->where('follower_id', $viewerId)
                                            ->where('status', 'accepted'));
                                });
                        });
                });

                $ownerQuery->where(function (Builder $petsVisibilityQuery) use ($viewerId): void {
                    $petsVisibilityQuery
                        ->where('users.id', $viewerId)
                        ->orWhereNull('pets_visibility')
                        ->orWhere('pets_visibility', 'everyone')
                        ->orWhere(function (Builder $followersOnlyQuery) use ($viewerId): void {
                            $followersOnlyQuery
                                ->where('pets_visibility', 'followers_only')
                                ->whereIn('users.id', Follow::query()
                                    ->select('following_id')
                                    ->where('follower_id', $viewerId)
                                    ->where('status', 'accepted'));
                        });
                });

                return;
            }

            $ownerQuery->where('is_private', false);
            $ownerQuery->where(function (Builder $petsVisibilityQuery): void {
                $petsVisibilityQuery
                    ->whereNull('pets_visibility')
                    ->orWhere('pets_visibility', 'everyone');
            });
        });

        if ($isAdmin) {
            return $query;
        }

        if ($viewerId > 0) {
            return $query->where(function (Builder $petQuery) use ($viewerId): void {
                $petQuery
                    ->where('pets.user_id', $viewerId)
                    ->orWhere(function (Builder $visibleQuery): void {
                        $visibleQuery
                            ->whereNull('pets.visibility')
                            ->orWhereIn('pets.visibility', ['public', 'followers_only'])
                            ->orWhereNull('pets.is_public')
                            ->orWhere('pets.is_public', true);
                    });
            });
        }

        return $query->where(function (Builder $visibleQuery): void {
            $visibleQuery
                ->whereNull('pets.visibility')
                ->orWhereIn('pets.visibility', ['public', 'followers_only'])
                ->orWhereNull('pets.is_public')
                ->orWhere('pets.is_public', true);
        });
    }

    private function petsVisibilityAllows(?User $viewer, User $owner): bool
    {
        $setting = $owner->pets_visibility ?: 'everyone';

        if ($setting === 'followers_only') {
            return $viewer && $viewer->isFollowing($owner);
        }

        return true;
    }

    private function canViewFullProfile(?User $viewer, Pet $pet): bool
    {
        if (! $this->canViewPetShell($viewer, $pet)) {
            return false;
        }

        $owner = $pet->owner;

        if (! $owner instanceof User) {
            return false;
        }

        if ($viewer instanceof User && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']) || $pet->isCoOwnedBy($viewer))) {
            return true;
        }

        return match ($this->petVisibility($pet)) {
            'public' => true,
            'followers_only' => $viewer instanceof User && $pet->isFollowedBy($viewer),
            default => false,
        };
    }

    private function petVisibility(Pet $pet): string
    {
        $visibility = (string) ($pet->getRawOriginal('visibility') ?: $pet->visibility);

        if (in_array($visibility, Pet::VISIBILITY, true)) {
            return $visibility;
        }

        $rawIsPublic = $pet->getRawOriginal('is_public');

        return in_array($rawIsPublic, [0, '0', false], true) ? 'private' : 'public';
    }
}
