<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserUnfollowed
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(
        public readonly User $follower,
        public readonly User $target,
        public readonly bool $wasFollowing,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'user.unfollowed';
    }

    public function actorId(): int
    {
        return (int) $this->follower->getKey();
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
            (int) $this->follower->getKey(),
            (int) $this->target->getKey(),
        ];
    }

    /**
     * @return array<string, int|string|bool>
     */
    public function payload(): array
    {
        return [
            'follower_id' => (int) $this->follower->getKey(),
            'target_id' => (int) $this->target->getKey(),
            'was_following' => $this->wasFollowing,
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
