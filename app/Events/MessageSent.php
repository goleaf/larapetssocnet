<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use App\Models\Identity\User;
use App\Models\Messaging\Message;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(
        public readonly Message $message,
        public readonly User $sender,
        public readonly User $receiver,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'message.sent';
    }

    public function actorId(): int
    {
        return (int) $this->sender->getKey();
    }

    public function subjectId(): int
    {
        return (int) $this->message->getKey();
    }

    /**
     * @return list<int>
     */
    public function relatedUserIds(): array
    {
        return [
            (int) $this->sender->getKey(),
            (int) $this->receiver->getKey(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function payload(): array
    {
        return [
            'message_id' => $this->subjectId(),
            'sender_id' => (int) $this->sender->getKey(),
            'receiver_id' => (int) $this->receiver->getKey(),
            'conversation_id' => (int) $this->message->getAttribute('conversation_id'),
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
