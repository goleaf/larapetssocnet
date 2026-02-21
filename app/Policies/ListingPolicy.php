<?php

namespace App\Policies;

use App\Models\MarketplaceListing;
use App\Models\User;

class ListingPolicy
{
    public function viewAny(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function view(?User $user, MarketplaceListing $listing): bool
    {
        if ($listing->status === MarketplaceListing::STATUS_ACTIVE) {
            return true;
        }

        if (! $user) {
            return false;
        }

        return (int) $listing->user_id === (int) $user->getKey()
            || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, MarketplaceListing $listing): bool
    {
        return (int) $listing->user_id === (int) $user->getKey()
            || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, MarketplaceListing $listing): bool
    {
        return $this->update($user, $listing);
    }

    public function restore(User $user, MarketplaceListing $listing): bool
    {
        return $this->update($user, $listing);
    }
}
