<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLoginLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $url)
    {
        $this->afterCommit();
    }

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
            ->subject('Your PetSocial sign-in link')
            ->line('Use this secure link to sign in to your PetSocial account.')
            ->line('This link expires in 15 minutes and can only be used once.')
            ->action('Sign in to PetSocial', $this->url)
            ->line('If you did not request this link, you can ignore this email.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'url' => $this->url,
        ];
    }
}
