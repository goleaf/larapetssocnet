<?php

namespace App\Services;

use App\Models\Contest;
use App\Models\Event;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;

class FeedService
{
    public function getFeed(User $user, ?string $type, int $perPage): array
    {
        $posts = Post::query()
            ->forFeed((int) $user->getKey())
            ->whereDoesntHave('author', fn ($query) => $query->where('is_banned', true))
            ->whereNotIn('user_id', $user->blocking()->select('users.id'))
            ->whereNotIn('user_id', $user->blockedBy()->select('users.id'))
            ->withFeedRelations()
            ->withFeedLikeExistsForViewer((int) (auth()->id() ?? $user->getKey()))
            ->when(in_array($type, ['text', 'photo', 'video'], true), fn ($query) => $query->byType($type))
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage)
            ->withQueryString();

        $posts->setCollection($posts->getCollection()->withoutAppends());

        $postIds = $posts->getCollection()->modelKeys();

        $myReactions = $user->reactions()
            ->whereIn('reactable_id', $postIds)
            ->where('reactable_type', Post::class)
            ->get()
            ->keyBy('reactable_id');

        $mySaved = $user->savedPosts()
            ->whereIn('posts.id', $postIds)
            ->pluck('posts.id')
            ->flip();

        return compact('posts', 'myReactions', 'mySaved');
    }

    public function getSidebarData(User $user): array
    {
        $suggestions = $user->getSuggestedUsersToFollow(4);
        $trending = Hashtag::query()->trending(6)->get();
        $events = Event::query()->upcoming()->published()->limit(2)->get();
        $contest = Contest::query()->active()->first();

        return compact('suggestions', 'trending', 'events', 'contest');
    }
}
