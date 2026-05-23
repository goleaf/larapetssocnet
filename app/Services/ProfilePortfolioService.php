<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\ProfilePortfolioPost;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfilePortfolioService
{
    public const MAX_POSTS = 12;

    /**
     * @return Collection<int, Post>
     */
    public function publicPosts(User $profileOwner): Collection
    {
        return $this->publicPortfolioQuery($profileOwner)
            ->join('profile_portfolio_posts', 'profile_portfolio_posts.post_id', '=', 'posts.id')
            ->where('profile_portfolio_posts.user_id', $profileOwner->getKey())
            ->addSelect('profile_portfolio_posts.display_order as portfolio_display_order')
            ->orderBy('profile_portfolio_posts.display_order')
            ->limit(self::MAX_POSTS)
            ->get();
    }

    /**
     * @return Collection<int, Post>
     */
    public function manageablePosts(User $user, int $limit = 60): Collection
    {
        return $this->publicPortfolioQuery($user)
            ->orderByDesc('posts.published_at')
            ->orderByDesc('posts.created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return list<int>
     */
    public function selectedPostIds(User $user): array
    {
        return ProfilePortfolioPost::query()
            ->where('user_id', $user->getKey())
            ->orderBy('display_order')
            ->pluck('post_id')
            ->map(fn (mixed $postId): int => (int) $postId)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function selectedPositions(User $user): array
    {
        return ProfilePortfolioPost::query()
            ->where('user_id', $user->getKey())
            ->orderBy('display_order')
            ->pluck('display_order', 'post_id')
            ->mapWithKeys(fn (mixed $order, mixed $postId): array => [(int) $postId => (int) $order])
            ->all();
    }

    /**
     * @param  list<int>  $postIds
     * @return list<int>
     */
    public function eligiblePostIds(User $user, array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        return $this->publicPortfolioQuery($user)
            ->whereIn('posts.id', array_values(array_unique($postIds)))
            ->pluck('posts.id')
            ->map(fn (mixed $postId): int => (int) $postId)
            ->all();
    }

    /**
     * @param  list<int>  $postIds
     * @param  array<int, int>  $positions
     */
    public function sync(User $user, array $postIds, array $positions = []): void
    {
        $orderedPostIds = $this->orderedPostIds($postIds, $positions);
        $eligiblePostIds = $this->eligiblePostIds($user, $orderedPostIds);
        $eligibleLookup = array_flip($eligiblePostIds);

        $rows = collect($orderedPostIds)
            ->filter(fn (int $postId): bool => array_key_exists($postId, $eligibleLookup))
            ->take(self::MAX_POSTS)
            ->values();

        DB::transaction(function () use ($user, $rows): void {
            ProfilePortfolioPost::query()
                ->where('user_id', $user->getKey())
                ->delete();

            $rows->each(function (int $postId, int $index) use ($user): void {
                ProfilePortfolioPost::query()->create([
                    'user_id' => $user->getKey(),
                    'post_id' => $postId,
                    'display_order' => $index + 1,
                ]);
            });
        });
    }

    /**
     * @return Builder<Post>
     */
    private function publicPortfolioQuery(User $profileOwner): Builder
    {
        return Post::query()
            ->profileTimelineColumns()
            ->forProfile($profileOwner)
            ->published()
            ->visibleTo(null)
            ->with([
                'author.media',
                'hashtags',
                'media',
                'postMedia',
                'pet' => fn ($petQuery) => $petQuery->visibleTo(null),
            ])
            ->withListEngagement();
    }

    /**
     * @param  list<int>  $postIds
     * @param  array<int, int>  $positions
     * @return list<int>
     */
    private function orderedPostIds(array $postIds, array $positions): array
    {
        return collect($postIds)
            ->map(fn (int $postId): int => $postId)
            ->unique()
            ->sortBy(fn (int $postId): int => $positions[$postId] ?? (self::MAX_POSTS + $postId))
            ->take(self::MAX_POSTS)
            ->values()
            ->all();
    }
}
