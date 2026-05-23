<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Analytics\ProfileWrappedSummary;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Social\Follow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ProfileWrappedService
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function yearBounds(int $year): array
    {
        $timezone = config('app.timezone');
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone)->startOfDay();

        return [$start, $start->endOfYear()];
    }

    public function reviewYearFor(?CarbonImmutable $now = null): int
    {
        return (int) ($now ?? CarbonImmutable::now())->subYear()->year;
    }

    public function isGenerationWindow(?CarbonImmutable $now = null): bool
    {
        $date = $now ?? CarbonImmutable::now();

        return (int) $date->month === 1 && (int) $date->day <= 7;
    }

    public function isDisplayWindow(?CarbonImmutable $now = null): bool
    {
        $date = $now ?? CarbonImmutable::now();

        return (int) $date->month === 1 && (int) $date->day <= 14;
    }

    public function generateForUser(User $user, int $year): ProfileWrappedSummary
    {
        $userId = (int) $user->getKey();
        $topReaction = $this->topReactionReceived($userId, $year);
        $activeMonth = $this->mostActiveMonth($userId, $year);
        $mostEngagedPost = $this->mostEngagedPost($userId, $year);

        /** @var ProfileWrappedSummary $summary */
        $summary = ProfileWrappedSummary::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'year' => $year,
            ],
            [
                'total_posts_published' => $this->totalPostsPublished($userId, $year),
                'total_reactions_received' => $this->totalReactionsReceived($userId, $year),
                'top_reaction_type' => $topReaction['type'],
                'top_reaction_count' => $topReaction['count'],
                'most_active_month' => $activeMonth['month'],
                'most_active_month_posts' => $activeMonth['posts'],
                'new_followers_count' => $this->newFollowersCount($userId, $year),
                'pets_added_count' => $this->petsAddedCount($userId, $year),
                'most_engaged_post_id' => $mostEngagedPost['post_id'],
                'most_engaged_post_score' => $mostEngagedPost['score'],
                'generated_at' => now(),
            ],
        );

        return $summary->refresh();
    }

    /**
     * @return array{from: string, via: string, to: string, text: string, muted: string}
     */
    public function identityGradientPalette(User $user): array
    {
        $palettes = [
            ['from' => '#f7ebe6', 'via' => '#fbf6ee', 'to' => '#eef5f1'],
            ['from' => '#f6e3c9', 'via' => '#fbf6ee', 'to' => '#f7ebe6'],
            ['from' => '#e7f1e8', 'via' => '#fbf6ee', 'to' => '#eef5f1'],
            ['from' => '#f5e6e3', 'via' => '#fbf6ee', 'to' => '#f6e3c9'],
            ['from' => '#eef5f1', 'via' => '#fbf6ee', 'to' => '#e7f1e8'],
        ];

        $seed = (string) ($user->username ?: $user->email ?: $user->getKey());
        $palette = $palettes[abs(crc32($seed)) % count($palettes)];

        return [
            ...$palette,
            'text' => '#201914',
            'muted' => '#4c4037',
        ];
    }

    private function totalPostsPublished(int $userId, int $year): int
    {
        return (int) $this->publishedPostsQuery($userId, $year)->count('posts.id');
    }

    private function totalReactionsReceived(int $userId, int $year): int
    {
        [$start, $end] = $this->yearBounds($year);

        return (int) Reaction::query()
            ->join('posts', 'posts.id', '=', 'reactions.reactable_id')
            ->where('reactions.reactable_type', (new Post)->getMorphClass())
            ->where('posts.user_id', $userId)
            ->whereNull('posts.deleted_at')
            ->whereBetween('reactions.created_at', [$start, $end])
            ->count('reactions.id');
    }

    /**
     * @return array{type: ?string, count: int}
     */
    private function topReactionReceived(int $userId, int $year): array
    {
        [$start, $end] = $this->yearBounds($year);

        $row = Reaction::query()
            ->select(['reactions.type'])
            ->selectRaw('COUNT(*) as aggregate')
            ->join('posts', 'posts.id', '=', 'reactions.reactable_id')
            ->where('reactions.reactable_type', (new Post)->getMorphClass())
            ->where('posts.user_id', $userId)
            ->whereNull('posts.deleted_at')
            ->whereBetween('reactions.created_at', [$start, $end])
            ->groupBy('reactions.type')
            ->orderByDesc('aggregate')
            ->orderBy('reactions.type')
            ->first();

        return [
            'type' => $row instanceof Reaction ? (string) $row->getAttribute('type') : null,
            'count' => $row instanceof Reaction ? (int) $row->getAttribute('aggregate') : 0,
        ];
    }

    /**
     * @return array{month: ?int, posts: int}
     */
    private function mostActiveMonth(int $userId, int $year): array
    {
        $row = $this->publishedPostsQuery($userId, $year)
            ->selectRaw("CAST(strftime('%m', COALESCE(posts.published_at, posts.created_at)) AS INTEGER) as active_month")
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('active_month')
            ->orderByDesc('aggregate')
            ->orderBy('active_month')
            ->first();

        $activeMonth = $row instanceof Post ? $row->getAttribute('active_month') : null;

        return [
            'month' => $activeMonth === null ? null : (int) $activeMonth,
            'posts' => $row instanceof Post ? (int) $row->getAttribute('aggregate') : 0,
        ];
    }

    private function newFollowersCount(int $userId, int $year): int
    {
        [$start, $end] = $this->yearBounds($year);

        return (int) Follow::query()
            ->accepted()
            ->where('following_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->count('id');
    }

    private function petsAddedCount(int $userId, int $year): int
    {
        [$start, $end] = $this->yearBounds($year);

        return (int) Pet::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->count('id');
    }

    /**
     * @return array{post_id: ?int, score: int}
     */
    private function mostEngagedPost(int $userId, int $year): array
    {
        [$start, $end] = $this->yearBounds($year);

        $reactionCounts = Reaction::query()
            ->selectRaw('reactions.reactable_id as post_id')
            ->selectRaw('COUNT(*) as reaction_total')
            ->where('reactions.reactable_type', (new Post)->getMorphClass())
            ->whereBetween('reactions.created_at', [$start, $end])
            ->groupBy('reactions.reactable_id');

        $commentCounts = Comment::query()
            ->selectRaw('comments.post_id')
            ->selectRaw('COUNT(*) as comment_total')
            ->whereBetween('comments.created_at', [$start, $end])
            ->groupBy('comments.post_id');

        $post = $this->publishedPostsQuery($userId, $year)
            ->leftJoinSub($reactionCounts, 'wrapped_reactions', function ($join): void {
                $join->on('posts.id', '=', 'wrapped_reactions.post_id');
            })
            ->leftJoinSub($commentCounts, 'wrapped_comments', function ($join): void {
                $join->on('posts.id', '=', 'wrapped_comments.post_id');
            })
            ->select(['posts.id'])
            ->selectRaw('COALESCE(wrapped_reactions.reaction_total, 0) + COALESCE(wrapped_comments.comment_total, 0) as wrapped_engagement_score')
            ->orderByDesc('wrapped_engagement_score')
            ->orderByDesc('posts.published_at')
            ->orderByDesc('posts.created_at')
            ->first();

        return [
            'post_id' => $post instanceof Post ? (int) $post->getKey() : null,
            'score' => $post instanceof Post ? (int) $post->getAttribute('wrapped_engagement_score') : 0,
        ];
    }

    /**
     * @return Builder<Post>
     */
    private function publishedPostsQuery(int $userId, int $year): Builder
    {
        [$start, $end] = $this->yearBounds($year);

        return Post::query()
            ->where('posts.user_id', $userId)
            ->where('posts.status', PostStatus::Published->value)
            ->where(function (Builder $dateQuery) use ($start, $end): void {
                $dateQuery
                    ->whereBetween('posts.published_at', [$start, $end])
                    ->orWhere(function (Builder $fallbackDateQuery) use ($start, $end): void {
                        $fallbackDateQuery
                            ->whereNull('posts.published_at')
                            ->whereBetween('posts.created_at', [$start, $end]);
                    });
            });
    }
}
