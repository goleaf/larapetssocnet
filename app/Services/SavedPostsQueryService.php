<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Content\SavedPost;
use App\Models\Identity\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SavedPostsQueryService
{
    public function paginateForViewer(User $viewer, int $perPage = 15): LengthAwarePaginator
    {
        return SavedPost::paginateForViewer($viewer, $perPage);
    }
}
