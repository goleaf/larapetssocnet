<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\Groups\Group;
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
