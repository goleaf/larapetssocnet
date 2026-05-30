<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Content\Reaction;
use App\Models\Identity\User;
use App\Support\Posts\PostEngagementComparisonSvg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PostAnalyticsService
{
    public function __construct(private readonly PostEngagementComparisonSvg $chart) {}

    /**
     * @return array{
     *     metric_cards: list<array{key: string, label: string, description: string, value: int}>,
     *     reactions: list<array{type: string, label: string, emoji: string, count: int}>,
     *     comparison: list<array{label: string, post: int, average: float}>,
     *     comparison_chart: ?string
     * }
     */
    public function summary(Post $post): array
    {
        $post = Post::query()
            ->whereKey($post->getKey())
            ->firstOrFail();

        $reactionTotal = (int) $post->reactions_count;
        $comments = (int) $post->comments_count;
        $shares = (int) $post->shares_count;
        $views = (int) $post->view_count;
        $reach = $this->estimatedReach($post);
        $comparison = $this->comparison($post, $views, $reactionTotal, $comments, $shares);

        return [
            'metric_cards' => [
                [
                    'key' => 'views',
                    'label' => 'Total views',
                    'description' => 'Feed and profile renders by non-authors.',
                    'value' => $views,
                ],
                [
                    'key' => 'reactions',
                    'label' => 'Total reactions',
                    'description' => 'All reaction types combined.',
                    'value' => $reactionTotal,
                ],
                [
                    'key' => 'comments',
                    'label' => 'Total comments',
                    'description' => 'Conversation replies on this post.',
                    'value' => $comments,
                ],
                [
                    'key' => 'shares',
                    'label' => 'Total shares',
                    'description' => 'Copies and reposts tracked for this post.',
                    'value' => $shares,
                ],
                [
                    'key' => 'reach',
                    'label' => 'Estimated reach',
                    'description' => 'Author followers plus repost audience.',
                    'value' => $reach,
                ],
            ],
            'reactions' => $this->reactionBreakdown($post),
            'comparison' => $comparison,
            'comparison_chart' => $this->chart->render($comparison),
        ];
    }

    private function estimatedReach(Post $post): int
    {
        $authorFollowers = (int) User::query()
            ->whereKey($post->user_id)
            ->value('followers_count');

        $reposterFollowerTotal = (int) User::query()
            ->whereIn('id', Post::query()
                ->select('user_id')
                ->where('original_post_id', $post->getKey())
                ->whereNull('deleted_at')
                ->where('status', PostStatus::Published->value))
            ->sum('followers_count');

        return $authorFollowers + $reposterFollowerTotal;
    }

    /**
     * @return list<array{type: string, label: string, emoji: string, count: int}>
     */
    private function reactionBreakdown(Post $post): array
    {
        return collect(Reaction::options())
            ->map(fn (array $reaction): array => [
                'type' => (string) $reaction['type'],
                'label' => (string) $reaction['label'],
                'emoji' => (string) $reaction['emoji'],
                'count' => (int) $post->getAttribute(Reaction::counterColumn((string) $reaction['type'])),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string, post: int, average: float}>
     */
    private function comparison(Post $post, int $views, int $reactions, int $comments, int $shares): array
    {
        $averagePosts = Post::query()
            ->where('user_id', $post->user_id)
            ->whereKeyNot($post->getKey())
            ->whereNull('deleted_at')
            ->where('status', PostStatus::Published->value)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->latest('published_at')
            ->latest('id')
            ->limit(10)
            ->get(['view_count', 'reactions_count', 'comments_count', 'shares_count']);

        return [
            [
                'label' => 'Views',
                'post' => $views,
                'average' => $this->average($averagePosts, 'view_count'),
            ],
            [
                'label' => 'Reactions',
                'post' => $reactions,
                'average' => $this->average($averagePosts, 'reactions_count'),
            ],
            [
                'label' => 'Comments',
                'post' => $comments,
                'average' => $this->average($averagePosts, 'comments_count'),
            ],
            [
                'label' => 'Shares',
                'post' => $shares,
                'average' => $this->average($averagePosts, 'shares_count'),
            ],
        ];
    }

    /**
     * @param  Collection<int, Post>  $posts
     */
    private function average(Collection $posts, string $column): float
    {
        if ($posts->isEmpty()) {
            return 0.0;
        }

        return round((float) $posts->avg($column), 1);
    }
}
