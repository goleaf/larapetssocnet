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

        ProfileView::query()->upsert([
            [
                'profile_user_id' => $this->profileUserId,
                'viewer_user_id' => $this->viewerUserId,
                'viewed_on' => $viewedOn,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], uniqueBy: [
            'profile_user_id',
            'viewer_user_id',
            'viewed_on',
        ], update: [
            'updated_at',
        ]);
    }
}
