<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TagsProcessed
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly string $taggableType,
        public readonly int $taggableId,
        public readonly array $tags,
    ) {}
}
