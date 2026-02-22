<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Notifications\BadgeAwarded;
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

            $this->award($user, $badge->slug);
        }
    }

    public function award(User $user, string $slug, ?User $awardedBy = null, ?string $note = null): void
    {
        $badge = Badge::where('slug', $slug)->first();

        if (! $badge || $user->hasBadge($slug)) {
            return;
        }

        DB::transaction(function () use ($user, $badge, $awardedBy, $note): void {
            $user->badges()->attach($badge->id, [
                'awarded_at' => now(),
                'awarded_by' => $awardedBy?->id,
                'note' => $note,
            ]);

            $user->notify(new BadgeAwarded($badge));
        });
    }

    public function checkPostMilestones(User $user): void
    {
        $count = (int) ($user->posts_count ?? 0);

        if ($count >= 1) {
            $this->award($user, 'first_post');
        }
        if ($count >= 10) {
            $this->award($user, 'ten_posts');
        }
        if ($count >= 100) {
            $this->award($user, 'hundred_posts');
        }
    }

    public function checkFollowerMilestones(User $user): void
    {
        $count = (int) ($user->followers_count ?? 0);

        if ($count >= 1) {
            $this->award($user, 'first_follower');
        }
        if ($count >= 100) {
            $this->award($user, 'popular');
        }
    }

    public function checkPetMilestones(User $user): void
    {
        $count = (int) ($user->pets_count ?? 0);

        if ($count >= 3) {
            $this->award($user, 'pet_lover');
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
