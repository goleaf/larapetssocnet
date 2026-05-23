<?php

namespace App\Console\Commands;

use App\Jobs\GenerateProfileWrappedImage;
use App\Models\Identity\User;
use App\Services\ProfileWrappedService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('profiles:wrapped-generate {--year= : Review year to generate} {--user= : Generate for one active user ID or username} {--sync-images : Generate share images synchronously instead of queueing them}')]
#[Description('Generate annual profile wrapped summaries and share images.')]
class GenerateProfileWrappedCommand extends Command
{
    public function handle(ProfileWrappedService $wrapped): int
    {
        $year = $this->yearOption($wrapped);

        if ($year === null) {
            return self::FAILURE;
        }

        $generated = 0;

        /** @var Builder<User> $query */
        $query = User::query()->active();
        $this->applyUserOption($query);

        $query->lazyById(100)->each(function (User $user) use ($wrapped, $year, &$generated): void {
            $summary = $wrapped->generateForUser($user, $year);

            if ((bool) $this->option('sync-images')) {
                GenerateProfileWrappedImage::dispatchSync((int) $summary->getKey());
            } else {
                GenerateProfileWrappedImage::dispatch((int) $summary->getKey());
            }

            $generated++;
        });

        if ($generated === 0) {
            $this->warn('No active users matched the profile wrapped generation criteria.');

            return (string) $this->option('user') !== '' ? self::FAILURE : self::SUCCESS;
        }

        $this->info("Generated {$generated} profile wrapped ".str('summary')->plural($generated)." for {$year}.");

        return self::SUCCESS;
    }

    private function yearOption(ProfileWrappedService $wrapped): ?int
    {
        $rawYear = $this->option('year');

        if ($rawYear === null || $rawYear === '') {
            return $wrapped->reviewYearFor();
        }

        if (! is_numeric($rawYear)) {
            $this->error('The --year option must be numeric.');

            return null;
        }

        $year = (int) $rawYear;

        if ($year < 2000 || $year > now()->year) {
            $this->error('The --year option must be between 2000 and the current year.');

            return null;
        }

        return $year;
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyUserOption(Builder $query): void
    {
        $user = trim((string) $this->option('user'));

        if ($user === '') {
            return;
        }

        $query->where(function (Builder $userQuery) use ($user): void {
            if (ctype_digit($user)) {
                $userQuery->whereKey((int) $user);

                return;
            }

            $userQuery->where('users.username', $user);
        });
    }
}
