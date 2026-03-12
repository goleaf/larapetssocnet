<?php

namespace App\Console\Commands;

use App\Services\SyncGroupCountersService;
use Illuminate\Console\Command;

class RebuildGroupCountersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rebuild-group-counters-command {--chunk=100 : Number of groups per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild group member and post counters.';

    /**
     * Execute the console command.
     */
    public function handle(SyncGroupCountersService $counters): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        $counters->rebuildAll($chunkSize);

        $this->info('Group counters rebuilt.');

        return self::SUCCESS;
    }
}
