<?php

declare(strict_types=1);

namespace App\Actions\Hashtags;

use App\Services\HashtagService;

class RecalculateHashtagUsageCountsAction
{
    public function __construct(private readonly HashtagService $hashtags) {}

    public function handle(): void
    {
        $this->hashtags->recalculateUsageCounts();
    }
}
