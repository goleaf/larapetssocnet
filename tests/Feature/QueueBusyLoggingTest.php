<?php

use App\Notifications\QueueBusyAlert;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

it('logs a warning when the queue busy event is dispatched', function (): void {
    Log::spy();

    event(new QueueBusy('database', 'default', 42));

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'Queue busy threshold exceeded.'
                && $context['connection'] === 'database'
                && $context['queue'] === 'default'
                && $context['size'] === 42;
        });
});

it('sends an on-demand queue busy alert email when configured', function (): void {
    config()->set('queue.monitor.alert_email', 'ops@example.com');
    Log::spy();
    Notification::fake();

    event(new QueueBusy('database', 'default', 42));

    Notification::assertSentOnDemand(
        QueueBusyAlert::class,
        function (QueueBusyAlert $notification, array $channels, object $notifiable): bool {
            return $notifiable->routes['mail'] === 'ops@example.com'
                && in_array('mail', $channels, true)
                && $notification->queueConnectionName === 'database'
                && $notification->queueName === 'default'
                && $notification->pendingJobsCount === 42;
        }
    );
});

it('does not send an on-demand queue busy alert email when not configured', function (): void {
    config()->set('queue.monitor.alert_email');
    Log::spy();
    Notification::fake();

    event(new QueueBusy('database', 'default', 42));

    Notification::assertNothingSent();
});

it('does not send an on-demand queue busy alert email when configured value is blank', function (): void {
    config()->set('queue.monitor.alert_email', '   ');
    Log::spy();
    Notification::fake();

    event(new QueueBusy('database', 'default', 42));

    Notification::assertNothingSent();
});
