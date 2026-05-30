<?php

declare(strict_types=1);

use App\Console\Commands\ExpirePetOwnerInvitationsCommand;
use App\Console\Commands\ExpirePetOwnershipTransfersCommand;
use App\Console\Commands\GenerateProfileWrappedCommand;
use App\Console\Commands\PublishScheduledPostsCommand;
use App\Console\Commands\SendDailyReactionSummariesCommand;
use App\Console\Commands\SendPetBirthdayNotificationsCommand;
use App\Console\Commands\SendPetHealthRemindersCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(GenerateProfileWrappedCommand::class)
    ->dailyAt('02:15')
    ->when(fn (): bool => now()->month === 1 && now()->day <= 7)
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(PublishScheduledPostsCommand::class)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(SendPetBirthdayNotificationsCommand::class)
    ->dailyAt((string) config('pets.birthday.notification_time', '08:00'))
    ->withoutOverlapping();

Schedule::command(ExpirePetOwnerInvitationsCommand::class)
    ->dailyAt('02:45')
    ->withoutOverlapping();

Schedule::command(ExpirePetOwnershipTransfersCommand::class)
    ->dailyAt('02:50')
    ->withoutOverlapping();

Schedule::command(SendPetHealthRemindersCommand::class)
    ->dailyAt((string) config('pets.health_reminders.notification_time', '09:00'))
    ->withoutOverlapping();

Schedule::command(SendDailyReactionSummariesCommand::class)
    ->hourly()
    ->withoutOverlapping();
