<?php

namespace App\Services;

use App\Models\SavedPost;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SavedPostsQueryService
{
    public function paginateForViewer(User $viewer, int $perPage = 15): LengthAwarePaginator
    {
        return SavedPost::paginateForViewer($viewer, $perPage);
    }
}
