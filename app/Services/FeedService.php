<?php

namespace App\Services;

use App\Models\Activities\Contest;
use App\Models\Activities\Event;
use App\Models\Content\Hashtag;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class FeedService
{
    public function getFeed(User $user, ?string $type, int $perPage, ?string $source = null, ?string $ranking = null): array
    {
        $posts = Post::query()
            ->forFeed((int) $user->getKey())
            ->forFeedSource((int) $user->getKey(), $source)
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
        $trending = Cache::remember(
            'feed:trending-hashtags',
            now()->addMinutes(10),
            fn (): Collection => Hashtag::query()->trending(10)->get()
        );
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
}
