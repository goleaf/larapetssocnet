<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;

class MarketplaceListingPolicy
{
    public function view(?User $user, MarketplaceListing $listing): bool
    {
        if ($listing->status === 'active') {
            return true;
        }

        if (! $user instanceof User) {
            return false;
        }

        return (int) $listing->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function create(User $user): bool
    {
        return ! empty($user->getKey());
    }

    public function update(User $user, MarketplaceListing $listing): bool
    {
        return (int) $listing->user_id === (int) $user->getKey() || $user->hasAnyRole(['admin', 'moderator']);
    }

    public function delete(User $user, MarketplaceListing $listing): bool
    {
        return $this->update($user, $listing);
    }
}
