<?php

namespace App\Notifications\Mail\Operations;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QueueBusyAlert extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $queueConnectionName,
        public readonly string $queueName,
        public readonly int $pendingJobsCount,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Queue busy alert: {$this->queueConnectionName}:{$this->queueName}")
            ->line('The queue monitor threshold was exceeded.')
            ->line("Connection: {$this->queueConnectionName}")
            ->line("Queue: {$this->queueName}")
            ->line("Jobs waiting: {$this->pendingJobsCount}");
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'connection' => $this->queueConnectionName,
            'queue' => $this->queueName,
            'size' => $this->pendingJobsCount,
        ];
    }
}
