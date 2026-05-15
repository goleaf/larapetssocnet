<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostDeleted
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(
        public readonly Post $post,
        public readonly User $deletedBy,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'post.deleted';
    }

    public function actorId(): int
    {
        return (int) $this->deletedBy->getKey();
    }

    public function subjectId(): int
    {
        return (int) $this->post->getKey();
    }

    /**
     * @return array<string, int|string|null>
     */
    public function payload(): array
    {
        return [
            'post_id' => $this->subjectId(),
            'deleted_by_id' => (int) $this->deletedBy->getKey(),
            'owner_id' => (int) $this->post->getAttribute('user_id'),
            'pet_id' => $this->post->getAttribute('pet_id') !== null ? (int) $this->post->getAttribute('pet_id') : null,
            'group_id' => $this->post->getAttribute('group_id') !== null ? (int) $this->post->getAttribute('group_id') : null,
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
