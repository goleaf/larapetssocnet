<?php

namespace App\Console\Commands;

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Notifications\PetBirthdayToday;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('pets:send-birthday-notifications')]
#[Description('Send owner notifications for pets whose birthday is today.')]
class SendPetBirthdayNotificationsCommand extends Command
{
    public function handle(): int
    {
        $today = now();
        $sent = 0;

        Pet::query()
            ->with('owner')
            ->where(function (Builder $query) use ($today): void {
                $query
                    ->where(function (Builder $birthDateQuery) use ($today): void {
                        $birthDateQuery
                            ->whereNotNull('birth_date')
                            ->whereMonth('birth_date', $today->month)
                            ->whereDay('birth_date', $today->day);
                    })
                    ->orWhere(function (Builder $dateOfBirthQuery) use ($today): void {
                        $dateOfBirthQuery
                            ->whereNotNull('date_of_birth')
                            ->whereMonth('date_of_birth', $today->month)
                            ->whereDay('date_of_birth', $today->day);
                    });
            })
            ->whereHas('owner', function (Builder $ownerQuery): void {
                User::applyAvailableForProfiles($ownerQuery);
            })
            ->chunkById(100, function ($pets) use (&$sent): void {
                foreach ($pets as $pet) {
                    if (! $pet->owner instanceof User) {
                        continue;
                    }

                    $pet->owner->notify(new PetBirthdayToday($pet));
                    $sent++;
                }
            });

        $this->components->info("Sent {$sent} pet birthday notifications.");

        return self::SUCCESS;
    }
}
