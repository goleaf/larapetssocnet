<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class PostPerformancePredictionService
{
    /**
     * @param  array{body: string, has_media: bool, scheduled_publish_at?: string|null}  $draft
     * @return array{message: string, type: string}|null
     */
    public function analyze(User $user, array $draft): ?array
    {
        $posts = Post::query()
            ->where('user_id', $user->getKey())
            ->where('status', PostStatus::Published->value)
            ->whereNull('deleted_at')
            ->latest('published_at')
            ->latest('id')
            ->limit(50)
            ->get(['id', 'body', 'type', 'published_at', 'reactions_count', 'comments_count', 'shares_count']);

        if ($posts->count() < 10) {
            return null;
        }

        $hasMedia = (bool) $draft['has_media'];
        $mediaInsight = $this->mediaInsight($posts, $hasMedia);

        if ($mediaInsight !== null) {
            return $mediaInsight;
        }

        $timeInsight = $this->timeInsight($posts, $draft['scheduled_publish_at'] ?? null);

        if ($timeInsight !== null) {
            return $timeInsight;
        }

        $mentionInsight = $this->mentionInsight($posts, $draft['body']);

        if ($mentionInsight !== null) {
            return $mentionInsight;
        }

        $hashtagCount = preg_match_all('/#[A-Za-z0-9_]+/', $draft['body'], $matches);

        if ($hashtagCount > 0) {
            return [
                'message' => 'Your hashtagged posts average '.$this->formatNumber($this->averageEngagement($this->postsWithHashtags($posts))).' engagement actions.',
                'type' => 'hashtags',
            ];
        }

        $lengthInsight = $this->lengthInsight($posts, $draft['body']);

        if ($lengthInsight !== null) {
            return $lengthInsight;
        }

        return [
            'message' => 'Based on your last 10+ posts, concise updates with a clear moment tend to perform consistently for you.',
            'type' => 'baseline',
        ];
    }

    /**
     * @param  EloquentCollection<int, Post>  $posts
     * @return array{message: string, type: string}|null
     */
    private function mediaInsight(EloquentCollection $posts, bool $hasMedia): ?array
    {
        if (! $hasMedia) {
            return null;
        }

        $mediaAverage = $this->averageEngagement($posts->filter(fn (Post $post): bool => in_array((string) $post->type, ['photo', 'video'], true)));
        $textAverage = $this->averageEngagement($posts->filter(fn (Post $post): bool => (string) $post->type === Post::TYPE_TEXT));

        if ($mediaAverage <= 0 || $textAverage <= 0 || $mediaAverage < ($textAverage * 1.5)) {
            return null;
        }

        $ratio = max(2, round($mediaAverage / $textAverage, 1));
        $label = $ratio === floor($ratio) ? number_format($ratio, 0) : number_format($ratio, 1);

        return [
            'message' => 'Posts with photos tend to get '.$label.'x more reactions for you.',
            'type' => 'media',
        ];
    }

    /**
     * @param  EloquentCollection<int, Post>  $posts
     * @return array{message: string, type: string}|null
     */
    private function timeInsight(EloquentCollection $posts, ?string $scheduledPublishAt): ?array
    {
        $scheduledAt = null;

        if (filled($scheduledPublishAt)) {
            try {
                $scheduledAt = CarbonImmutable::parse((string) $scheduledPublishAt);
            } catch (\Throwable) {
                $scheduledAt = null;
            }
        }

        $weekdayMorningPosts = $posts->filter(function (Post $post): bool {
            $publishedAt = $post->getAttribute('published_at');

            return $publishedAt instanceof CarbonInterface
                && $publishedAt->isWeekday()
                && (int) $publishedAt->format('G') >= 6
                && (int) $publishedAt->format('G') < 12;
        });

        if ($weekdayMorningPosts->count() < 3) {
            return null;
        }

        $weekdayMorningAverage = $this->averageEngagement($weekdayMorningPosts);
        $overallAverage = $this->averageEngagement($posts);

        if ($weekdayMorningAverage <= 0 || $weekdayMorningAverage < ($overallAverage * 1.2)) {
            return null;
        }

        if ($scheduledAt instanceof CarbonImmutable && (! $scheduledAt->isWeekday() || (int) $scheduledAt->format('G') >= 12)) {
            return [
                'message' => 'Your posts on weekday mornings typically get the most engagement.',
                'type' => 'timing',
            ];
        }

        return [
            'message' => 'Weekday mornings are one of your strongest posting windows.',
            'type' => 'timing',
        ];
    }

    /**
     * @param  EloquentCollection<int, Post>  $posts
     * @return array{message: string, type: string}|null
     */
    private function mentionInsight(EloquentCollection $posts, string $body): ?array
    {
        if (preg_match('/@[A-Za-z0-9][A-Za-z0-9-]*/', $body) !== 1) {
            return null;
        }

        $mentionAverage = $this->averageEngagement($posts->filter(fn (Post $post): bool => preg_match('/@[A-Za-z0-9][A-Za-z0-9-]*/', (string) $post->body) === 1));
        $overallAverage = $this->averageEngagement($posts);

        if ($mentionAverage <= 0 || $mentionAverage < ($overallAverage * 1.1)) {
            return null;
        }

        return [
            'message' => 'Posts where you mention someone tend to perform a little better for you.',
            'type' => 'mentions',
        ];
    }

    /**
     * @param  EloquentCollection<int, Post>  $posts
     * @return array{message: string, type: string}|null
     */
    private function lengthInsight(EloquentCollection $posts, string $body): ?array
    {
        $draftLength = mb_strlen(trim($body));

        if ($draftLength === 0 || $draftLength > 180) {
            return null;
        }

        $shortAverage = $this->averageEngagement($posts->filter(fn (Post $post): bool => mb_strlen(trim((string) $post->body)) <= 180));
        $longAverage = $this->averageEngagement($posts->filter(fn (Post $post): bool => mb_strlen(trim((string) $post->body)) > 180));

        if ($shortAverage <= 0 || $longAverage <= 0 || $shortAverage < ($longAverage * 1.2)) {
            return null;
        }

        return [
            'message' => 'Your shorter posts usually earn stronger engagement than your longer updates.',
            'type' => 'length',
        ];
    }

    /**
     * @param  iterable<Post>  $posts
     */
    private function averageEngagement(iterable $posts): float
    {
        $count = 0;
        $total = 0;

        foreach ($posts as $post) {
            $count++;
            $total += (int) $post->reactions_count + (int) $post->comments_count + (int) $post->shares_count;
        }

        return $count > 0 ? round($total / $count, 1) : 0.0;
    }

    /**
     * @param  EloquentCollection<int, Post>  $posts
     * @return EloquentCollection<int, Post>
     */
    private function postsWithHashtags(EloquentCollection $posts): EloquentCollection
    {
        return $posts->filter(fn (Post $post): bool => preg_match('/#[A-Za-z0-9_]+/', (string) $post->body) === 1);
    }

    private function formatNumber(float $value): string
    {
        return floor($value) === $value ? number_format($value, 0) : number_format($value, 1);
    }
}
