<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostUnliked
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly Post $post,
        public readonly bool $wasLiked,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'post.unliked';
    }

    public function actorId(): int
    {
        return (int) $this->user->getKey();
    }

    public function subjectId(): int
    {
        return (int) $this->post->getKey();
    }

    /**
     * @return list<int>
     */
    public function relatedUserIds(): array
    {
        return array_values(array_unique([
            (int) $this->user->getKey(),
            (int) $this->post->getAttribute('user_id'),
        ]));
    }

    /**
     * @return array<string, int|string|bool>
     */
    public function payload(): array
    {
        return [
            'post_id' => $this->subjectId(),
            'user_id' => (int) $this->user->getKey(),
            'post_owner_id' => (int) $this->post->getAttribute('user_id'),
            'was_liked' => $this->wasLiked,
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
