<?php

namespace App\Jobs;

use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use App\Notifications\GroupDigestReady;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendGroupDigest implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $groupId,
        public readonly CarbonInterface|string $windowStart,
        public readonly CarbonInterface|string $windowEnd,
    ) {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $group = Group::query()->find($this->groupId);

        if (! $group instanceof Group) {
            return;
        }

        $windowStart = $this->asCarbon($this->windowStart);
        $windowEnd = $this->asCarbon($this->windowEnd);

        $postCount = Post::query()
            ->where('group_id', $group->getKey())
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->count();

        if ($postCount === 0) {
            return;
        }

        $recipientIds = GroupMember::query()
            ->forGroup((int) $group->getKey())
            ->active()
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds->all())
            ->get(['id', 'name', 'username']);

        Notification::send($recipients, new GroupDigestReady($group, $postCount, $windowStart, $windowEnd));
    }

    private function asCarbon(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($value);
    }
}
