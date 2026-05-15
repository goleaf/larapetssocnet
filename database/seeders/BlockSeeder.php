<?php

namespace Database\Seeders;

use App\Models\Identity\User;
use App\Services\BlockService;
use App\Services\CounterCacheService;
use Illuminate\Database\Seeder;
use Throwable;

class BlockSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->where('is_banned', false)
            ->where('username', '!=', 'admin')
            ->get();

        $blockService = app(BlockService::class);

        $users->each(function (User $user) use ($users, $blockService): void {
            $targets = $users
                ->where('id', '!=', $user->id)
                ->random(random_int(0, min(2, max($users->count() - 1, 0))));

            foreach ($targets as $target) {
                try {
                    $blockService->block($user, $target);
                } catch (Throwable) {
                    // Skip invalid block attempts.
                }
            }
        });

        app(CounterCacheService::class)->rebuildAll();
    }
}
