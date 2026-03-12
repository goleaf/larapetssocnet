<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PetVisibilityService
{
    public function canView(?User $viewer, Pet $pet): bool
    {
        $pet->loadMissing('owner');

        $owner = $pet->owner;
        if (! $owner) {
            return false;
        }

        if ((bool) $owner->is_banned) {
            return false;
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($owner)) {
            return false;
        }

        if ($viewer && ($viewer->is($owner) || $viewer->hasAnyRole(['admin', 'moderator']))) {
            return true;
        }

        $rawIsPublic = $pet->getRawOriginal('is_public');

        if (in_array($rawIsPublic, [0, '0', false], true)) {
            return false;
        }

        if (! $owner->canViewProfile($viewer)) {
            return false;
        }

        return $this->petsVisibilityAllows($viewer, $owner);
    }

    public function canViewFollowers(?User $viewer, Pet $pet): bool
    {
        if (! $this->canView($viewer, $pet)) {
            return false;
        }

        if (! $viewer) {
            return false;
        }

        return $viewer->is($pet->owner) || $viewer->hasAnyRole(['admin', 'moderator']);
    }

    public function canViewPetsForOwner(?User $viewer, User $owner): bool
    {
        if ((bool) $owner->is_banned) {
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

    public function applyVisibleScope(Builder $query, ?User $viewer): Builder
    {
        $query->select(['pets.*']);

        $viewerId = (int) ($viewer?->getKey() ?? 0);
        $isAdmin = $viewer?->hasAnyRole(['admin', 'moderator']) ?? false;

        if ($viewer && (bool) $viewer->is_banned) {
            return $query->whereKey(-1);
        }

        $query->whereHas('owner', function (Builder $ownerQuery) use ($viewerId, $isAdmin): void {
            $ownerQuery->where('is_banned', false);

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
                                        ->whereHas('acceptedFollowers', function (Builder $followersQuery) use ($viewerId): Builder {
                                            return $followersQuery->where('users.id', $viewerId);
                                        });
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
                                ->whereHas('acceptedFollowers', function (Builder $followersQuery) use ($viewerId): Builder {
                                    return $followersQuery->where('users.id', $viewerId);
                                });
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
                        $visibleQuery->whereNull('pets.is_public')->orWhere('pets.is_public', true);
                    });
            });
        }

        return $query->where(function (Builder $visibleQuery): void {
            $visibleQuery->whereNull('pets.is_public')->orWhere('pets.is_public', true);
        });
    }

    private function petsVisibilityAllows(?User $viewer, User $owner): bool
    {
        $setting = $owner->pets_visibility ?: 'everyone';

        if ($setting === 'followers_only') {
            return $viewer ? $viewer->isFollowing($owner) : false;
        }

        return true;
    }
}
