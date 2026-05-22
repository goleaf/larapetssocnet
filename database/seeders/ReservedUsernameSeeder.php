<?php

namespace Database\Seeders;

use App\Models\Identity\ReservedUsername;
use App\Support\Usernames\UsernameRules;
use Illuminate\Database\Seeder;

class ReservedUsernameSeeder extends Seeder
{
    public function run(): void
    {
        foreach (UsernameRules::reservedList() as $username) {
            ReservedUsername::query()->updateOrCreate(
                ['username' => $username],
                ['reason' => 'configured', 'created_at' => now()]
            );
        }
    }
}
