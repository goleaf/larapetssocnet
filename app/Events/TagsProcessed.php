<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TagsProcessed
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $taggableType,
        public readonly int $taggableId,
        public readonly array $tags,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'tags.processed';
    }

    public function subjectId(): int
    {
        return $this->taggableId;
    }

    /**
     * @return array<string, int|string|list<string>>
     */
    public function payload(): array
    {
        return [
            'taggable_type' => $this->taggableType,
            'taggable_id' => $this->taggableId,
            'tags' => $this->tags,
            'tag_count' => count($this->tags),
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
