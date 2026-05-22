<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class ProfilePostOrderingService
{
    public function apply(Builder $query): Builder
    {
        return $query
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id');
    }
}
