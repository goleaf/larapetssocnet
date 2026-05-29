<?php

namespace App\Console\Commands;

use App\Services\Pets\PetOwnerInvitationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pets:expire-owner-invitations')]
#[Description('Expire pending pet co-owner invitations whose 14-day window has elapsed.')]
class ExpirePetOwnerInvitationsCommand extends Command
{
    public function handle(PetOwnerInvitationService $invitations): int
    {
        $expired = $invitations->expirePending();

        $this->components->info("Expired {$expired} pet co-owner invitation(s).");

        return self::SUCCESS;
    }
}
