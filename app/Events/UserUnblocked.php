<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUnblocked
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(
        public readonly User $actor,
        public readonly User $target,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'user.unblocked';
    }

    public function actorId(): int
    {
        return (int) $this->actor->getKey();
    }

    public function subjectId(): int
    {
        return (int) $this->target->getKey();
    }

    /**
     * @return list<int>
     */
    public function relatedUserIds(): array
    {
        return [
            (int) $this->actor->getKey(),
            (int) $this->target->getKey(),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function payload(): array
    {
        return [
            'actor_id' => (int) $this->actor->getKey(),
            'target_id' => (int) $this->target->getKey(),
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
