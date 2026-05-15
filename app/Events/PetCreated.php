<?php

declare(strict_types=1);

namespace App\Events;

use App\Events\Concerns\HasEventMetadata;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PetCreated
{
    use Dispatchable;
    use HasEventMetadata;
    use SerializesModels;

    public function __construct(
        public readonly Pet $pet,
        public readonly User $owner,
        ?CarbonImmutable $occurredAt = null,
    ) {
        $this->occurredAt = $occurredAt ?? CarbonImmutable::now();
    }

    public function eventName(): string
    {
        return 'pet.created';
    }

    public function actorId(): int
    {
        return (int) $this->owner->getKey();
    }

    public function subjectId(): int
    {
        return (int) $this->pet->getKey();
    }

    /**
     * @return array<string, int|string|null>
     */
    public function payload(): array
    {
        return [
            'pet_id' => $this->subjectId(),
            'owner_id' => (int) $this->owner->getKey(),
            'name' => $this->pet->getAttribute('name') !== null ? (string) $this->pet->getAttribute('name') : null,
            'species' => $this->pet->getAttribute('species') !== null ? (string) $this->pet->getAttribute('species') : null,
            'occurred_at' => $this->occurredAtIso(),
        ];
    }
}
