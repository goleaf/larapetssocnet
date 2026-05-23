<?php

namespace App\Mail\Auth;

use App\Models\Identity\User;
use App\Models\Security\LoginSecurityAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginAnomalySecurityAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly LoginSecurityAlert $alert,
        public readonly string $dismissUrl,
        public readonly string $secureUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New PetSocial login from '.$this->alert->country,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.login-anomaly-alert',
            with: [
                'user' => $this->user,
                'alert' => $this->alert,
                'dismissUrl' => $this->dismissUrl,
                'secureUrl' => $this->secureUrl,
            ],
        );
    }
}
