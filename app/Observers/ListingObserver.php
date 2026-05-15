<?php

namespace App\Observers;

use App\Models\Marketplace\Listing;

class ListingObserver
{
    public function saving(Listing $listing): void
    {
        if (! $listing->exists || $listing->isDirty('title') || blank($listing->slug)) {
            $listing->slug = Listing::generateUniqueSlug(
                (string) ($listing->title ?: $listing->slug ?: 'listing'),
                $listing->exists ? (int) $listing->getKey() : null,
            );
        }
    }
}
