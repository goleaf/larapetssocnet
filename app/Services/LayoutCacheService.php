<?php

namespace App\Services;

use App\Enums\GroupMemberStatus;
use App\Models\Activities\Contest;
use App\Models\Activities\Event;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Groups\Group;
use App\Models\Identity\User;
use App\Support\Caching\CacheCatalog;
use Illuminate\Cache\TaggableStore;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class LayoutCacheService
{
    private const string TAGS = 'layout';

    public function forRequest(?User $user): array
    {
        return [
            'communityStats' => $this->communityStats(),
            'trendingHashtags' => $this->trendingHashtags(),
            'upcomingEvents' => $this->upcomingEvents(),
            'activeContests' => $this->activeContests(),
            'suggestedUsers' => $user instanceof User ? $this->suggestedUsers((int) $user->getKey()) : collect(),
            'yourGroups' => $user instanceof User ? $this->yourGroups((int) $user->getKey()) : collect(),
        ];
    }

    public function forgetForRequest(?User $user): void
    {
        $this->forgetGlobal();

        if ($user instanceof User) {
            $this->forgetUserAware((int) $user->getKey());
        }
    }

    public function forgetGlobal(): void
    {
        $this->forgetWithTags($this->globalKeys());
    }

    private function communityStats(): array
    {
        return CacheCatalog::remember($this->communityStatsKey(), now()->addSeconds(
            CacheCatalog::ttl('layout.community_stats', 300),
        ), function (): array {
            return [
                ['label' => 'Members', 'value' => Schema::hasTable('users') ? number_format((int) User::query()->count()) : '--'],
                ['label' => 'Pets', 'value' => Schema::hasTable('pets') ? number_format((int) PetQuery::countVisible()) : '--'],
                ['label' => 'Posts', 'value' => Schema::hasTable('posts') ? number_format((int) Post::query()->count()) : '--'],
            ];
        });
    }

    private function trendingHashtags(): array
    {
        if (! Schema::hasTable('hashtags')) {
            return [];
        }

        return CacheCatalog::remember(
            $this->trendingHashtagsKey(),
            now()->addSeconds(CacheCatalog::ttl('layout.trending_hashtags', 300)),
            fn (): array => Hashtag::query()
                ->select(['id', 'name', 'slug', 'posts_count'])
                ->orderByDesc('posts_count')
                ->limit(5)
                ->get()
                ->all(),
        );
    }

    private function upcomingEvents(): array
    {
        if (! Schema::hasTable('events')) {
            return [];
        }

        return CacheCatalog::remember(
            $this->upcomingEventsKey(),
            now()->addSeconds(CacheCatalog::ttl('layout.upcoming_events', 180)),
            fn (): array => Event::query()
                ->select(['id', 'title', 'start_at', 'location_text', 'attendees_count'])
                ->where('start_at', '>=', now())
                ->orderBy('start_at')
                ->limit(2)
                ->get()
                ->all(),
        );
    }

    private function activeContests(): array
    {
        if (! Schema::hasTable('contests')) {
            return [];
        }

        return CacheCatalog::remember(
            $this->activeContestsKey(),
            now()->addSeconds(CacheCatalog::ttl('layout.active_contests', 300)),
            fn (): array => Contest::query()
                ->select(['id', 'title', 'slug', 'status', 'ends_at', 'entries_count'])
                ->whereIn('status', ['active', 'voting'])
                ->orderBy('ends_at')
                ->limit(2)
                ->get()
                ->all(),
        );
    }

    private function suggestedUsers(int $userId): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return CacheCatalog::remember(
            $this->suggestedUsersKey($userId),
            now()->addSeconds(CacheCatalog::ttl('layout.suggested_users', 180)),
            fn (): array => User::query()
                ->select(['id', 'name', 'username', 'avatar_path', 'followers_count'])
                ->where('id', '!=', $userId)
                ->where('is_private', false)
                ->where('is_banned', false)
                ->orderByDesc('followers_count')
                ->limit(3)
                ->get()
                ->all(),
        );
    }

    private function yourGroups(int $userId): array
    {
        if (! Schema::hasTable('groups') || ! Schema::hasTable('group_members')) {
            return [];
        }

        return CacheCatalog::remember(
            $this->yourGroupsKey($userId),
            now()->addSeconds(CacheCatalog::ttl('layout.user_groups', 180)),
            fn (): array => Group::query()
                ->select(['groups.id', 'groups.name', 'groups.slug', 'groups.privacy', 'groups.members_count'])
                ->whereIn('groups.id', function ($query) use ($userId): void {
                    $query->select('group_members.group_id')
                        ->from('group_members')
                        ->where('group_members.user_id', $userId)
                        ->where(function ($statusQuery): void {
                            $statusQuery
                                ->whereNull('group_members.status')
                                ->orWhereIn('group_members.status', GroupMemberStatus::activeValues());
                        });
                })
                ->orderByDesc('groups.members_count')
                ->limit(6)
                ->get()
                ->all(),
        );
    }

    private function forgetUserAware(int $userId): void
    {
        CacheCatalog::forget($this->suggestedUsersKey($userId));
        CacheCatalog::forget($this->yourGroupsKey($userId));
    }

    private function forgetWithTags(array $keys): void
    {
        foreach ($keys as $key) {
            if (CacheCatalog::supportsTags()) {
                Cache::tags([self::TAGS])->forget($key);

                continue;
            }

            Cache::forget($key);
        }

        if (CacheCatalog::supportsTags()) {
            Cache::tags([self::TAGS])->flush();
        }
    }

    private function communityStatsKey(): string
    {
        return CacheCatalog::key('layout', 'community-stats');
    }

    private function trendingHashtagsKey(): string
    {
        return CacheCatalog::key('layout', 'trending-hashtags');
    }

    private function upcomingEventsKey(): string
    {
        return CacheCatalog::key('layout', 'upcoming-events');
    }

    private function activeContestsKey(): string
    {
        return CacheCatalog::key('layout', 'active-contests');
    }

    private function suggestedUsersKey(int $userId): string
    {
        return CacheCatalog::key('layout', 'suggested-users', ['user' => $userId]);
    }

    private function yourGroupsKey(int $userId): string
    {
        return CacheCatalog::key('layout', 'your-groups', ['user' => $userId]);
    }

    /**
     * @return array<int, string>
     */
    private function globalKeys(): array
    {
        return [
            $this->communityStatsKey(),
            $this->trendingHashtagsKey(),
            $this->upcomingEventsKey(),
            $this->activeContestsKey(),
        ];
    }
}

class PetQuery
{
    public static function countVisible(): int
    {
        return (int) (\App\Models\Pets\Pet::query()
            ->select(['id'])
            ->selectRaw(new Expression('COUNT(*)'))
            ->limit(1)
            ->value(new Expression('count(*)')));
    }
}
