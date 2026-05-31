<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Seeding\SeedProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:seed-demo {--profile=demo : Seed profile (tiny|demo|test|performance)} {--confirm-performance : Explicitly allow the performance profile in non-local/testing environments}')]
#[Description('Seed database using a predefined dataset profile.')]
class SeedDemoCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $profile = SeedProfile::resolve((string) $this->option('profile'));

        if (! $profile instanceof SeedProfile) {
            $this->error('Invalid seeding profile. Use one of: tiny, demo, test, performance.');

            return self::FAILURE;
        }

        $confirmPerformance = (bool) $this->option('confirm-performance');

        if (! $profile->isAllowedInCurrentEnvironment($this->laravel, $confirmPerformance)) {
            $this->error('The selected seeding profile is not allowed in this environment. Use --confirm-performance in non-production safe environments.');

            return self::FAILURE;
        }

        config([
            'seeding.profile' => $profile->value,
            'seeding.performance_confirmation' => $confirmPerformance,
        ]);

        return $this->call('db:seed', [
            '--force' => true,
        ]);
    }
}
