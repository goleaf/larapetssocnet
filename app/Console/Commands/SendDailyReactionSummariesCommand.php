<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyReactionSummaryJob;
use App\Models\Identity\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('reactions:send-daily-summaries {--force : Dispatch for every opted-in user regardless of local hour}')]
#[Description('Dispatch optional daily reaction summary emails for heavy reactors at 8pm local time.')]
class SendDailyReactionSummariesCommand extends Command
{
    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $dispatched = 0;

        User::query()
            ->whereNotNull('notification_preferences')
            ->select(['id', 'timezone', 'notification_preferences'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use ($force, &$dispatched): void {
                foreach ($users as $user) {
                    if (! $user instanceof User || ! $user->notificationPreference('daily_reaction_summary', false)) {
                        continue;
                    }

                    $timezone = filled($user->timezone) ? (string) $user->timezone : (string) config('app.timezone', 'UTC');
                    $now = Carbon::now($timezone);

                    if (! $force && (int) $now->format('G') !== 20) {
                        continue;
                    }

                    SendDailyReactionSummaryJob::dispatch((int) $user->getKey(), $now->toDateString());
                    $dispatched++;
                }
            });

        $this->components->info("Dispatched {$dispatched} daily reaction summary job(s).");

        return self::SUCCESS;
    }
}
