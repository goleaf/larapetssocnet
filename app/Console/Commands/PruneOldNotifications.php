<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOldNotifications extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Delete notifications older than 90 days';

    public function handle(): int
    {
        // DB::table permitted for maintenance commands only — not application logic.
        $deleted = DB::table('notifications')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        $this->info("Deleted {$deleted} old notifications.");

        return self::SUCCESS;
    }
}
