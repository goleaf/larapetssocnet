<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillUsernames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-usernames';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing or invalid usernames with safe, unique values';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $processed = 0;
        $updated = 0;

        User::query()
            ->select(['id', 'name', 'display_name', 'email', 'username'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$processed, &$updated): void {
                foreach ($users as $user) {
                    $processed++;
                    $current = (string) $user->username;
                    $normalized = UsernameNormalizer::normalize($current);

                    if ($normalized === '') {
                        $seed = (string) ($user->display_name ?: $user->name ?: Str::before((string) $user->email, '@'));
                        $normalized = UsernameNormalizer::generateBase($seed);
                    }

                    if ($normalized === '') {
                        $normalized = 'petlover';
                    }

                    $candidate = $normalized;

                    if (! UsernameRules::isAvailable($candidate, $user->id)) {
                        $candidate = User::generateUniqueUsername($candidate);
                    }

                    if ($candidate !== $user->username) {
                        $user->updateQuietly(['username' => $candidate]);
                        $updated++;
                    }
                }
            });

        $this->info("Processed {$processed} users.");
        $this->info("Updated {$updated} usernames.");

        return self::SUCCESS;
    }
}
