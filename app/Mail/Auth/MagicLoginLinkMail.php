<?php

declare(strict_types=1);

namespace App\Mail\Auth;

use App\Enums\Support\Queue\QueueName;
use App\Models\Identity\User;
use App\Support\Queue\HasDefaultQueueRuntime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLoginLinkMail extends Mailable implements ShouldQueue
{
    use HasDefaultQueueRuntime;
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $loginUrl,
    ) {
        $this->onQueue(QueueName::Mail->routingName());
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PetSocial login link',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.magic-login-link',
            with: [
                'user' => $this->user,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
