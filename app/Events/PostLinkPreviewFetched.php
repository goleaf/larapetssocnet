<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PostLinkPreviewFetched
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    /**
     * @param  array<string, string>  $preview
     */
    public function __construct(
        public readonly int $postId,
        public readonly array $preview,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'post.link-preview-fetched';
    }

    public function actorId(): int
    {
        return 0;
    }

    public function subjectId(): int
    {
        return $this->postId;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function payload(): array
    {
        return [
            'post_id' => $this->postId,
            'url' => $this->preview['url'] ?? null,
            'domain' => $this->preview['domain'] ?? null,
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
