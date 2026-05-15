<?php

namespace App\Events\Concerns;

use Carbon\CarbonImmutable;

trait HasEventMetadata
{
    public readonly CarbonImmutable $occurredAt;

    public function occurredAtIso(): string
    {
        return $this->occurredAt->toIso8601String();
    }
}
