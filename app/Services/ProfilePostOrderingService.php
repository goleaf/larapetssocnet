<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class ProfilePostOrderingService
{
    public function apply(Builder $query): Builder
    {
        return $query
            ->orderByDesc('posts.is_pinned')
            ->orderByDesc('posts.pinned_at')
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id');
    }
}
