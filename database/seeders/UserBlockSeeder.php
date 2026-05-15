<?php

namespace Database\Seeders;

use App\Models\Identity\User;
use App\Models\Social\UserBlock;
use Illuminate\Database\Seeder;

class UserBlockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->count() < 2) {
            return;
        }

        $numBlocks = rand(3, 5);

        for ($i = 0; $i < $numBlocks; $i++) {
            $blocker = $users->random();
            $blocked = $users->except($blocker->id)->random();

            UserBlock::firstOrCreate([
                'blocker_id' => $blocker->id,
                'blocked_id' => $blocked->id,
            ]);
        }
    }
}
