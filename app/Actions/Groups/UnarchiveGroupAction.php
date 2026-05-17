<?php

namespace App\Actions\Groups;

use App\Models\Groups\Group;
use Illuminate\Support\Facades\DB;

class UnarchiveGroupAction
{
    public function handle(Group $group): Group
    {
        return DB::transaction(function () use ($group): Group {
            $group->unarchive();

            return $group->refresh();
        });
    }
}
