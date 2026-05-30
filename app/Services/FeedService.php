<?php

namespace App\Services;

use App\Models\Activities\Contest;
use App\Models\Activities\Event;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use BadMethodCallException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeedService
{
    private const TRENDING_HASHTAGS_CACHE_KEY = 'feed:trending-hashtags';

    /**
     * @var list<string>
     */
    private const TRENDING_HASHTAGS_CACHE_TAGS = ['feed', 'hashtags'];

    public function getFeed(User $user, ?string $type, int $perPage, ?string $source = null, ?string $ranking = null): array
    {
        $posts = Post::query()
            ->forFeed((int) $user->getKey(), $source)
            ->whereDoesntHave('author', fn ($query) => $query->where('is_banned', true))
            ->whereNotIn('user_id', $user->blocking()->select('users.id'))
            ->whereNotIn('user_id', $user->blockedBy()->select('users.id'))
            ->withFeedRelations($user)
            ->withFeedLikeExistsForViewer((int) (auth()->id() ?? $user->getKey()))
            ->when(in_array($type, ['text', 'photo', 'video'], true), fn ($query) => $query->byType($type))
            ->orderForMainFeed($ranking ?? $user->preferredFeedRanking())
            ->cursorPaginate($perPage)
            ->withQueryString();

        $posts->setCollection($posts->getCollection()->withoutAppends());

        $postIds = $posts->getCollection()->modelKeys();

        $myReactions = $user->reactions()
            ->whereIn('reactable_id', $postIds)
            ->where('reactable_type', (new Post)->getMorphClass())
            ->get()
            ->keyBy('reactable_id');

        $mySaved = $user->savedPosts()
            ->whereIn('posts.id', $postIds)
            ->pluck('posts.id')
            ->flip();

        return ['posts' => $posts, 'myReactions' => $myReactions, 'mySaved' => $mySaved];
    }

    public function getSidebarData(User $user): array
    {
        $suggestions = $user->getSuggestedUsersToFollow(5);
        $trending = $this->trendingHashtags();
        $events = Event::query()->upcoming()->published()->limit(2)->get();
        $contest = Contest::query()->active()->first();
        $upcomingBirthdays = $this->upcomingPetBirthdays($user);

        return [
            'suggestions' => $suggestions,
            'trending' => $trending,
            'events' => $events,
            'contest' => $contest,
            'upcomingBirthdays' => $upcomingBirthdays,
        ];
    }

    /**
     * @return Collection<int, Hashtag>
     */
    public function trendingHashtags(): Collection
    {
        return collect($this->trendingHashtagRows())
            ->map(fn (array $attributes): Hashtag => (new Hashtag)->newFromBuilder($attributes));
    }

    public function flushTrendingHashtagsCache(): void
    {
        if ($this->cacheSupportsTags()) {
            Cache::tags(self::TRENDING_HASHTAGS_CACHE_TAGS)->flush();

            return;
        }

        Cache::forget(self::TRENDING_HASHTAGS_CACHE_KEY);
    }

    /**
     * @return Collection<int, User>
     */
    public function contextualEmptyFeedSuggestions(User $user, int $limit = 4): Collection
    {
        $species = $user->pets()
            ->without(['user', 'species', 'breed', 'media', 'tags'])
            ->select(['pets.species'])
            ->whereNotNull('pets.species')
            ->distinct()
            ->pluck('pets.species')
            ->filter()
            ->values();

        if ($species->isEmpty()) {
            return collect();
        }

        return User::query()
            ->discoverable()
            ->notBlockedFor($user)
            ->whereKeyNot($user->getKey())
            ->whereNotIn('users.id', $user->acceptedFollowing()->select('users.id'))
            ->whereHas('pets', function ($petQuery) use ($species): void {
                $petQuery->whereIn('pets.species', $species->all());
            })
            ->with('media')
            ->orderByDesc('users.followers_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Pet>
     */
    private function upcomingPetBirthdays(User $user): Collection
    {
        $today = Carbon::today();
        $birthdayKeys = collect(range(0, 7))
            ->map(fn (int $offset): string => $today->copy()->addDays($offset)->format('m-d'))
            ->values();

        $daysByBirthdayKey = $birthdayKeys
            ->flip()
            ->map(fn (int $offset): int => $offset);

        $pets = Pet::query()
            ->without(['user', 'species', 'breed', 'media', 'tags'])
            ->with(['media'])
            ->visibleTo($user)
            ->whereIn('pets.user_id', $user->acceptedFollowing()->select('users.id'))
            ->whereIn('pets.birthday_month_day', $birthdayKeys->all())
            ->where('pets.is_archived', false)
            ->orderBy('pets.birthday_month_day')
            ->limit(20)
            ->get();

        return $pets
            ->each(function (Pet $pet) use ($daysByBirthdayKey): void {
                $daysUntil = (int) ($daysByBirthdayKey->get((string) $pet->birthday_month_day) ?? 0);

                $pet->setAttribute('days_until_birthday', $daysUntil);
            })
            ->sortBy(fn (Pet $pet): int => (int) $pet->getAttribute('days_until_birthday'))
            ->take(5)
            ->values();
    }

    private function cacheSupportsTags(): bool
    {
        try {
            Cache::tags(self::TRENDING_HASHTAGS_CACHE_TAGS);

            return true;
        } catch (BadMethodCallException) {
            return false;
        }
    }

    /**
     * @return list<array{id: int, name: string, slug: string|null, normalized_name: string|null, posts_count: int, created_at: string|null, updated_at: string|null}>
     */
    private function trendingHashtagRows(): array
    {
        $resolver = fn (): array => Hashtag::query()
            ->select(['id', 'name', 'slug', 'normalized_name', 'posts_count', 'created_at', 'updated_at'])
            ->trending(10)
            ->get()
            ->map(fn (Hashtag $hashtag): array => $this->hashtagCacheRow($hashtag))
            ->all();

        $rows = $this->cacheSupportsTags()
            ? Cache::tags(self::TRENDING_HASHTAGS_CACHE_TAGS)
                ->remember(self::TRENDING_HASHTAGS_CACHE_KEY, now()->addMinutes(10), $resolver)
            : Cache::remember(self::TRENDING_HASHTAGS_CACHE_KEY, now()->addMinutes(10), $resolver);

        return $this->normalizeTrendingHashtagRows($rows);
    }

    /**
     * @return array{id: int, name: string, slug: string|null, normalized_name: string|null, posts_count: int, created_at: string|null, updated_at: string|null}
     */
    private function hashtagCacheRow(Hashtag $hashtag): array
    {
        $attributes = $hashtag->getAttributes();

        return [
            'id' => (int) $attributes['id'],
            'name' => (string) $attributes['name'],
            'slug' => isset($attributes['slug']) ? (string) $attributes['slug'] : null,
            'normalized_name' => isset($attributes['normalized_name']) ? (string) $attributes['normalized_name'] : null,
            'posts_count' => (int) ($attributes['posts_count'] ?? 0),
            'created_at' => isset($attributes['created_at']) ? (string) $attributes['created_at'] : null,
            'updated_at' => isset($attributes['updated_at']) ? (string) $attributes['updated_at'] : null,
        ];
    }

    /**
     * @return list<array{id: int, name: string, slug: string|null, normalized_name: string|null, posts_count: int, created_at: string|null, updated_at: string|null}>
     */
    private function normalizeTrendingHashtagRows(mixed $rows): array
    {
        return collect($rows)
            ->map(function (mixed $row): ?array {
                if ($row instanceof Hashtag) {
                    return $this->hashtagCacheRow($row);
                }

                if (! is_array($row) || ! isset($row['id'], $row['name'])) {
                    return null;
                }

                return [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'slug' => isset($row['slug']) ? (string) $row['slug'] : null,
                    'normalized_name' => isset($row['normalized_name']) ? (string) $row['normalized_name'] : null,
                    'posts_count' => (int) ($row['posts_count'] ?? 0),
                    'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
                    'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }
}
