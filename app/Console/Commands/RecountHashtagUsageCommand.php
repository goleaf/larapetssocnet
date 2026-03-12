<?php

namespace App\Console\Commands;

use App\Actions\Hashtags\RecalculateHashtagUsageCountsAction;
use Illuminate\Console\Command;

class RecountHashtagUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hashtags:recount-usage';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate hashtag usage counts for published posts.';

    /**
     * Execute the console command.
     */
    public function handle(RecalculateHashtagUsageCountsAction $recalculate): int
    {
        $recalculate->handle();

        $this->info('Hashtag usage counts recalculated.');

        return self::SUCCESS;
    }
}
