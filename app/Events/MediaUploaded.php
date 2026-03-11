<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaUploaded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Media $media,
        public readonly string $type,
        public readonly int $ownerId,
    ) {}
}
