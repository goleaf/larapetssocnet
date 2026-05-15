<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use App\Models\Content\Post;
use BackedEnum;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostCreated
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(public readonly Post $post, ?CarbonImmutable $occurredAt = null)
    {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'post.created';
    }

    public function actorId(): int
    {
        return (int) $this->post->getAttribute('user_id');
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
        $status = $this->post->getAttribute('status');

        return [
            'post_id' => $this->subjectId(),
            'user_id' => (int) $this->post->getAttribute('user_id'),
            'pet_id' => $this->post->getAttribute('pet_id') !== null ? (int) $this->post->getAttribute('pet_id') : null,
            'group_id' => $this->post->getAttribute('group_id') !== null ? (int) $this->post->getAttribute('group_id') : null,
            'status' => $status instanceof BackedEnum ? (string) $status->value : (string) $status,
            'visibility' => (string) $this->post->getAttribute('visibility'),
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
