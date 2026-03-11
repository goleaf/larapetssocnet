<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

$defaultQueueConnection = (string) config('queue.default', 'database');
$defaultQueueName = (string) config("queue.connections.{$defaultQueueConnection}.queue", 'default');
$queueMonitorTarget = (string) (config('queue.monitor.queues') ?: "{$defaultQueueConnection}:{$defaultQueueName}");
$queueMonitorMax = (int) config('queue.monitor.max', 100);

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('queue:monitor', [
    'queues' => $queueMonitorTarget,
    '--max' => $queueMonitorMax,
])->everyMinute();
