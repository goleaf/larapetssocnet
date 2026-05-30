<?php

namespace App\Services;

use App\Models\Analytics\ProfileView;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;

class ProfileViewRecorder
{
    public function record(int $profileUserId, int $viewerUserId, ?string $viewedOn = null): void
    {
        if ($profileUserId === $viewerUserId) {
            return;
        }

        $profileUser = User::query()
            ->select(['id', 'timezone'])
            ->find($profileUserId);

        if (! $profileUser instanceof User) {
            return;
        }

        $timezone = $profileUser->timezone ?: config('app.timezone');
        $viewedOn = $viewedOn !== null
            ? CarbonImmutable::parse($viewedOn, $timezone)->toDateString()
            : CarbonImmutable::now($timezone)->toDateString();

        $recordedAt = now();
        $inserted = ProfileView::query()->insertOrIgnore([
            [
                'profile_user_id' => $profileUserId,
                'viewer_user_id' => $viewerUserId,
                'viewed_on' => $viewedOn,
                'views_count' => 1,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ],
        ]);

        if ($inserted === 0) {
            ProfileView::query()
                ->where('profile_user_id', $profileUserId)
                ->where('viewer_user_id', $viewerUserId)
                ->where('viewed_on', $viewedOn)
                ->increment('views_count', 1, ['updated_at' => $recordedAt]);
        }
    }
}
