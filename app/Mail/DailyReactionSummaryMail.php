<?php

namespace App\Mail;

use App\Enums\Support\Queue\QueueName;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Support\Queue\HasDefaultQueueRuntime;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyReactionSummaryMail extends Mailable implements ShouldQueue
{
    use HasDefaultQueueRuntime;
    use Queueable;
    use SerializesModels;

    /**
     * @param  Collection<int, Post>  $posts
     */
    public function __construct(
        public readonly User $user,
        public readonly Collection $posts,
        public readonly string $summaryDate,
    ) {
        $this->onQueue(QueueName::Mail->routingName());
        $this->afterCommit();
    }

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
