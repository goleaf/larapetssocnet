<?php

namespace App\Events;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PetCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Pet $pet,
        public readonly User $owner,
    ) {}
}
