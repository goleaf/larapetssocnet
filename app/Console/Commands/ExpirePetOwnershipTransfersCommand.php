<?php

namespace App\Console\Commands;

use App\Services\Pets\PetOwnershipTransferService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pets:expire-ownership-transfers')]
#[Description('Expire pending pet ownership transfers whose 7-day acceptance window has elapsed.')]
class ExpirePetOwnershipTransfersCommand extends Command
{
    public function handle(PetOwnershipTransferService $transfers): int
    {
        $expired = $transfers->expirePending();

        $this->components->info("Expired {$expired} pet ownership transfer(s).");

        return self::SUCCESS;
    }
}
