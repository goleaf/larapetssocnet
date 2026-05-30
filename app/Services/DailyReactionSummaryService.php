<?php

namespace App\Services;

use App\Mail\DailyReactionSummaryMail;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class DailyReactionSummaryService
{
    public function send(int $userId, string $localDate): bool
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'timezone', 'notification_preferences'])
            ->find($userId);

        if (! $user instanceof User || ! $user->notificationPreference('daily_reaction_summary', false)) {
            return false;
        }

        $timezone = filled($user->timezone) ? (string) $user->timezone : (string) config('app.timezone', 'UTC');
        $localDay = CarbonImmutable::parse($localDate, $timezone);
        $start = $localDay->startOfDay()->utc();
        $end = $localDay->endOfDay()->utc();

        $postIds = Reaction::query()
            ->where('user_id', $user->getKey())
            ->where('reactable_type', (new Post)->getMorphClass())
            ->whereBetween('created_at', [$start, $end])
            ->distinct()
            ->pluck('reactable_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->values();

        if ($postIds->count() <= 20) {
            return false;
        }

        $cacheKey = sprintf('users:%d:daily-reaction-summary:%s', $user->getKey(), $localDay->toDateString());

        if (! Cache::add($cacheKey, true, now()->addHours(26))) {
            return false;
        }

        $posts = Post::query()
            ->with('author')
            ->whereIn('id', $postIds)
            ->orderByDesc('reactions_count')
            ->orderByDesc('comments_count')
            ->orderByDesc('shares_count')
            ->limit(5)
            ->get();

        if ($posts->isEmpty()) {
            return false;
        }

        Mail::to($user->email)->send(new DailyReactionSummaryMail($user, $posts, $localDay->toDateString()));

        return true;
    }
}
