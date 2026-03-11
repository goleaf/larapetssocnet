<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('registers queue monitor in the scheduler', function (): void {
    expect(config('queue.monitor'))->toBeArray();
    expect(config('queue.monitor.max'))->toBeInt();

    $this->artisan('schedule:list')
        ->expectsOutputToContain('queue:monitor')
        ->assertExitCode(0);
});

it('reports oldest pending job in queue monitor json output', function (): void {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.queue', 'default');

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes(5)->timestamp,
        'created_at' => now()->subMinutes(5)->timestamp,
    ]);

    Artisan::call('queue:monitor', [
        'queues' => 'database:default',
        '--max' => 0,
        '--json' => true,
    ]);

    $output = trim((string) Artisan::output());

    /** @var array<int, array<string, mixed>> $payload */
    $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($payload)->toHaveCount(1);
    expect($payload[0]['connection'])->toBe('database');
    expect($payload[0]['queue'])->toBe('default');
    expect($payload[0]['pending'])->toBe(1);
    expect($payload[0]['status'])->toBe('ALERT');
    expect($payload[0]['oldest_pending'])->toBeInt();
});

it('prints oldest pending job in queue monitor text output', function (): void {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.queue', 'default');

    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->subMinutes(2)->timestamp,
        'created_at' => now()->subMinutes(2)->timestamp,
    ]);

    $this->artisan('queue:monitor', [
        'queues' => 'database:default',
        '--max' => 0,
    ])
        ->expectsOutputToContain('Oldest pending job')
        ->assertExitCode(0);
});

it('respects queue monitor env overrides in schedule list output', function (): void {
    $process = new Process([PHP_BINARY, 'artisan', 'schedule:list'], base_path(), [
        'QUEUE_MONITOR_QUEUES' => 'database:critical',
        'QUEUE_MONITOR_MAX' => '250',
    ]);

    $process->run();

    expect($process->getExitCode())->toBe(0);
    expect($process->getOutput())->toContain("queues='database:critical'");
    expect($process->getOutput())->toContain('--max=250');
});
