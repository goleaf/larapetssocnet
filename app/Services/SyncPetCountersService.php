<?php

namespace App\Services;

use App\Models\Pet;

class SyncPetCountersService
{
    public function sync(Pet $pet): Pet
    {
        $pet->loadCount([
            'followers as computed_followers',
            'posts as computed_posts',
        ]);

        $pet->updateQuietly([
            'followers_count' => (int) $pet->computed_followers,
            'posts_count' => (int) $pet->computed_posts,
        ]);

        return $pet->refresh();
    }
}
