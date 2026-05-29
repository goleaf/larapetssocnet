<?php

namespace App\Console\Commands;

use App\Services\Pets\PetHealthReminderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('pets:send-health-reminders')]
#[Description('Send due pet health reminders to pet co-owners.')]
class SendPetHealthRemindersCommand extends Command
{
    public function handle(PetHealthReminderService $reminders): int
    {
        $sent = $reminders->sendDueReminders();

        $this->components->info("Sent {$sent} pet health reminder notifications.");

        return self::SUCCESS;
    }
}
