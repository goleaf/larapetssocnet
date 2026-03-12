<?php

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\DB;

class DeleteGroupAction
{
    public function handle(Group $group): void
    {
        DB::transaction(function () use ($group): void {
            $group->delete();
        });
    }
}
