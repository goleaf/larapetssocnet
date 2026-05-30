<?php

namespace App\Jobs;

use App\Models\Analytics\ProfileView;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordProfileView implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $profileUserId,
        public int $viewerUserId,
        public ?string $viewedOn = null,
    ) {}

    public function handle(): void
    {
        if ($this->profileUserId === $this->viewerUserId) {
            return;
        }

        $profileUser = User::query()
            ->select(['id', 'timezone'])
            ->find($this->profileUserId);

        if (! $profileUser instanceof User) {
            return;
        }

        $timezone = $profileUser->timezone ?: config('app.timezone');
        $viewedOn = $this->viewedOn !== null
            ? CarbonImmutable::parse($this->viewedOn, $timezone)->toDateString()
            : CarbonImmutable::now($timezone)->toDateString();

        $recordedAt = now();
        $inserted = ProfileView::query()->insertOrIgnore([
            [
                'profile_user_id' => $this->profileUserId,
                'viewer_user_id' => $this->viewerUserId,
                'viewed_on' => $viewedOn,
                'views_count' => 1,
                'created_at' => $recordedAt,
                'updated_at' => $recordedAt,
            ],
        ]);

        if ($inserted === 0) {
            ProfileView::query()
                ->where('profile_user_id', $this->profileUserId)
                ->where('viewer_user_id', $this->viewerUserId)
                ->where('viewed_on', $viewedOn)
                ->increment('views_count', 1, ['updated_at' => $recordedAt]);
        }
    }
}
