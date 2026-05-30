<?php

use App\Notifications\Mail\Operations\QueueBusyAlert;
use Illuminate\Notifications\AnonymousNotifiable;

it('builds queue busy alert mail message with queue details', function (): void {
    $notification = new QueueBusyAlert('database', 'critical', 99);
    $mailMessage = $notification->toMail(new AnonymousNotifiable);

    expect($mailMessage->subject)->toBe('Queue busy alert: database:critical');
    expect($mailMessage->introLines)->toBe([
        'The queue monitor threshold was exceeded.',
        'Connection: database',
        'Queue: critical',
        'Jobs waiting: 99',
    ]);
});

it('exposes queue details in array payload', function (): void {
    $notification = new QueueBusyAlert('database', 'critical', 99);

    expect($notification->toArray(new AnonymousNotifiable))->toBe([
        'connection' => 'database',
        'queue' => 'critical',
        'size' => 99,
    ]);
});
