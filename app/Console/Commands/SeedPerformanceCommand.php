<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Seeding\SeedProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:seed-performance {--confirm-performance : Explicitly allow unsafe performance seeding}')]
#[Description('Seed database using the performance dataset profile.')]
class SeedPerformanceCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $profile = SeedProfile::Performance;
        $confirmPerformance = (bool) $this->option('confirm-performance');

        if (! $profile->isAllowedInCurrentEnvironment($this->laravel, $confirmPerformance)) {
            $this->error('Performance seeding requires --confirm-performance in non-local/testing environments.');

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
