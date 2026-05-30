<?php

use App\Enums\PostStatus;
use App\Enums\Support\Queue\QueueName;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\Maintenance\MaintenanceTaskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('marks realtime maintenance tasks without a console scheduler', function (): void {
    $tasks = app(MaintenanceTaskService::class)->tasks();

    expect($tasks['publish-scheduled-posts']['realtime'])->toBeTrue()
        ->and($tasks['prune-deleted-accounts']['realtime'])->toBeTrue()
        ->and($tasks['prune-old-notifications']['realtime'])->toBeTrue();
});

it('monitors class routed queues by default', function (): void {
    expect(QueueName::workerOrder())->toBe([
        QueueName::Mail->value,
        QueueName::Notifications->value,
        QueueName::Comments->value,
        QueueName::Default->value,
    ])
        ->and(array_map(fn (QueueName $queue): int => $queue->priority(), QueueName::prioritized()))->toBe([10, 20, 30, 100])
        ->and(QueueName::workerQueueOption())->toBe('mail,notifications,comments,default')
        ->and(config('queue.monitor.queues'))->toBe(QueueName::monitorQueueOption());
});

it('stores failed jobs in the database with uuid-backed records', function (): void {
    expect(config('queue.failed.driver'))->toBe('database-uuids')
        ->and(config('queue.failed.table'))->toBe('failed_jobs')
        ->and(Schema::hasTable('failed_jobs'))->toBeTrue()
        ->and(Schema::hasColumns('failed_jobs', [
            'uuid',
            'connection',
            'queue',
            'payload',
            'exception',
            'failed_at',
        ]))->toBeTrue();
});

it('reports oldest pending job in queue monitor json output', function (): void {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.queue', 'default');
    Log::spy();

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

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Queue busy threshold exceeded.'
            && $context['connection'] === 'database'
            && $context['queue'] === 'default'
            && $context['size'] === 1);
});

it('prints oldest pending job in queue monitor text output', function (): void {
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.queue', 'default');
    Log::spy();

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

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'Queue busy threshold exceeded.'
            && $context['connection'] === 'database'
            && $context['queue'] === 'default'
            && $context['size'] === 1);
});

it('runs due realtime maintenance without the scheduler', function (): void {
    foreach (MaintenanceTaskService::REALTIME_TASKS as $task) {
        Cache::forget("maintenance:realtime:{$task}");
    }

    $author = User::factory()->create();
    $post = Post::factory()->for($author)->create([
        'status' => PostStatus::Scheduled->value,
        'published_at' => now()->subMinute(),
    ]);

    app(MaintenanceTaskService::class)->runRealtimeDueTasks();

    expect($post->refresh()->status)->toBe(PostStatus::Published);
});
