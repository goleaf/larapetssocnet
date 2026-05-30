<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Groups\GroupMember;
use App\Models\Identity\User;
use App\Notifications\GroupDigestReady;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Notification;

class GroupDigestService
{
    public function send(int $groupId, CarbonInterface|string $windowStart, CarbonInterface|string $windowEnd): bool
    {
        $group = Group::query()->find($groupId);

        if (! $group instanceof Group) {
            return false;
        }

        $windowStart = $this->asCarbon($windowStart);
        $windowEnd = $this->asCarbon($windowEnd);

        $postCount = Post::query()
            ->where('group_id', $group->getKey())
            ->whereBetween('created_at', [$windowStart, $windowEnd])
            ->count();

        if ($postCount === 0) {
            return false;
        }

        $recipientIds = GroupMember::query()
            ->forGroup((int) $group->getKey())
            ->active()
            ->pluck('user_id')
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return false;
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds->all())
            ->get(['id', 'name', 'username']);

        Notification::send($recipients, new GroupDigestReady($group, $postCount, $windowStart, $windowEnd));

        return true;
    }

    private function asCarbon(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse($value);
    }
}
