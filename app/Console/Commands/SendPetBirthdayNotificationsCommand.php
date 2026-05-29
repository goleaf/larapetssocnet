<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPetBirthday;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

#[Signature('pets:send-birthday-notifications')]
#[Description('Send owner notifications for pets whose birthday is today.')]
class SendPetBirthdayNotificationsCommand extends Command
{
    public function handle(): int
    {
        $lock = Cache::lock('pets:birthday-notifications:'.now()->toDateString(), 3600);

        if (! $lock->get()) {
            Log::info('Skipped pet birthday notification command because another run owns the lock.');
            $this->components->warn('Pet birthday notification command is already running.');

            return self::SUCCESS;
        }

        Log::info('Acquired pet birthday notification command lock.');

        try {
            $today = now();
            $dispatched = 0;

            $pets = Pet::query()
                ->select(['id', 'user_id', 'birth_date', 'date_of_birth', 'birthday_month_day'])
                ->when(Schema::hasColumn('pets', 'is_archived'), fn (Builder $query): Builder => $query->where('is_archived', false));

            if (Schema::hasColumn('pets', 'birthday_month_day')) {
                $pets->where('birthday_month_day', $today->format('m-d'));
            } else {
                $pets->where(function (Builder $query) use ($today): void {
                    $query->where(function (Builder $birthDateQuery) use ($today): void {
                        $birthDateQuery
                            ->whereNotNull('birth_date')
                            ->whereMonth('birth_date', $today->month)
                            ->whereDay('birth_date', $today->day);
                    })->orWhere(function (Builder $dateOfBirthQuery) use ($today): void {
                        $dateOfBirthQuery
                            ->whereNotNull('date_of_birth')
                            ->whereMonth('date_of_birth', $today->month)
                            ->whereDay('date_of_birth', $today->day);
                    });
                });
            }

            $pets
                ->whereHas('owner', function (Builder $ownerQuery): void {
                    User::applyAvailableForProfiles($ownerQuery);
                })
                ->chunkById(100, function ($pets) use (&$dispatched): void {
                    foreach ($pets as $pet) {
                        ProcessPetBirthday::dispatch((int) $pet->getKey());
                        $dispatched++;
                    }
                });

            $this->components->info("Dispatched {$dispatched} pet birthday jobs.");

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
