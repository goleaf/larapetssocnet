<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use App\Models\Identity\User;
use App\Models\Social\Follow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserFollowed
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(
        public readonly Follow $follow,
        public readonly User $follower,
        public readonly User $target,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'user.followed';
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
     * @return array<string, int|string>
     */
    public function payload(): array
    {
        return [
            'follow_id' => (int) $this->follow->getKey(),
            'follower_id' => (int) $this->follower->getKey(),
            'target_id' => (int) $this->target->getKey(),
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
