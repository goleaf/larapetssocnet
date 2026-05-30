<?php

namespace App\Actions\Onboarding;

use App\Models\Identity\User;

class MarkPetReminderShownAction
{
    public function handle(User $user): void
    {
        if (! (bool) $user->onboarding_pet_reminder_pending || $user->onboarding_pet_reminder_shown_at !== null) {
            return;
        }

        $user->forceFill([
            'onboarding_pet_reminder_shown_at' => now(),
        ])->saveQuietly();
    }
}
