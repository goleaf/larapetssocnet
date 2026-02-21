<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    public function checkAndAwardBadges(User $user): void
    {
        $badges = Badge::query()->get();

        foreach ($badges as $badge) {
            if ($user->badges()->whereKey($badge->getKey())->exists()) {
                continue;
            }

            if (! $this->meetsCondition($user, $badge)) {
                continue;
            }

            DB::transaction(function () use ($badge, $user): void {
                $user->badges()->syncWithoutDetaching([
                    $badge->getKey() => [
                        'awarded_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            });
        }
    }

    private function meetsCondition(User $user, Badge $badge): bool
    {
        $value = (int) ($badge->condition_value ?? 0);

        return match ($badge->condition_type) {
            'posts_count' => (int) ($user->posts_count ?? 0) >= $value,
            'followers', 'followers_count' => (int) ($user->followers_count ?? 0) >= $value,
            'pet_count', 'pets_count' => (int) ($user->pets_count ?? 0) >= $value,
            'manual' => false,
            default => false,
        };
    }
}
