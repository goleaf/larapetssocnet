<?php

namespace App\Mail;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyReactionSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Post>  $posts
     */
    public function __construct(
        public readonly User $user,
        public readonly Collection $posts,
        public readonly string $summaryDate,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your PetSocial reaction roundup',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reactions.daily-summary',
        );
    }
}
