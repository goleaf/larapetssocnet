<?php

namespace App\Mail\Auth;

use App\Models\Identity\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordChangedSecurityAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $emergencyUrl,
        public readonly CarbonInterface $changedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PetSocial password was changed',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.auth.password-changed-alert',
            with: [
                'user' => $this->user,
                'emergencyUrl' => $this->emergencyUrl,
                'changedAt' => $this->changedAt,
            ],
        );
    }
}
