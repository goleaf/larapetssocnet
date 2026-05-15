<?php

namespace App\Services;

use App\Models\Identity\User;
use App\Models\Identity\UsernameChange;

class UsernameChangeService
{
    public function record(
        User $user,
        string $oldUsername,
        string $newUsername,
        ?User $actor = null,
        ?string $reason = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): UsernameChange {
        return UsernameChange::query()->create([
            'user_id' => $user->getKey(),
            'old_username' => $oldUsername,
            'new_username' => $newUsername,
            'changed_by' => $actor?->getKey(),
            'reason' => $reason,
            'changed_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
