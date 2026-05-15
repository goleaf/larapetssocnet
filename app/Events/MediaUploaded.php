<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaUploaded
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(
        public readonly Media $media,
        public readonly string $type,
        public readonly int $ownerId,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'media.uploaded';
    }

    public function actorId(): int
    {
        return $this->ownerId;
    }

    public function subjectId(): int
    {
        return (int) $this->media->getKey();
    }

    /**
     * @return array<string, int|string|null>
     */
    public function payload(): array
    {
        return [
            'media_id' => $this->subjectId(),
            'type' => $this->type,
            'owner_id' => $this->ownerId,
            'model_type' => (string) $this->media->getAttribute('model_type'),
            'model_id' => $this->media->getAttribute('model_id') !== null ? (int) $this->media->getAttribute('model_id') : null,
            'collection' => (string) $this->media->getAttribute('collection_name'),
            'mime_type' => $this->media->getAttribute('mime_type') !== null ? (string) $this->media->getAttribute('mime_type') : null,
            'size' => $this->media->getAttribute('size') !== null ? (int) $this->media->getAttribute('size') : null,
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
