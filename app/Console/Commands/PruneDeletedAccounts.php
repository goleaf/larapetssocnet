<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PruneDeletedAccounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounts:prune-deleted';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete user accounts that have passed their deletion grace period.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $users = User::query()
            ->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->get();

        $count = $users->count();

        foreach ($users as $user) {
            $user->clearMediaCollection('avatar');
            $user->clearMediaCollection('cover');
            $user->clearMediaCollection('photos');

            $user->forceDelete();
        }

        $this->info("Successfully pruned {$count} deleted accounts.");

        return self::SUCCESS;
    }
}
