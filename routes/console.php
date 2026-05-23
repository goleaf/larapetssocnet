<?php

declare(strict_types=1);

use App\Console\Commands\GenerateProfileWrappedCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(GenerateProfileWrappedCommand::class)
    ->dailyAt('02:15')
    ->when(fn (): bool => now()->month === 1 && now()->day <= 7)
    ->withoutOverlapping()
    ->runInBackground();
