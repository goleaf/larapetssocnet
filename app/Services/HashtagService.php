<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Hashtag;
use App\Models\Post;
use App\Models\User;
use App\Support\Hashtags\HashtagNormalizer;
use App\Support\Hashtags\HashtagParser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class HashtagService
{
    public function __construct(
        private readonly HashtagParser $parser,
        private readonly HashtagNormalizer $normalizer
    ) {}

    public function syncHashtags(Post $post, bool $updateCounts = true): void
    {
        $tags = $this->extract($post->body ?? '');

        $ids = collect($tags)
            ->map(fn (string $tag) => $this->resolveHashtagId($tag))
            ->filter()
            ->values();

        $existing = $post->hashtags()->pluck('hashtags.id');
        $attach = $ids->diff($existing);
        $detach = $existing->diff($ids);

        $post->hashtags()->sync($ids);

        if (! $updateCounts || ! $this->isEligibleForUsage($post)) {
            return;
        }

        $this->incrementUsage($attach->all());
        $this->decrementUsage($detach->all());
    }

    /**
     * @return list<string>
     */
    public function extract(string $text): array
    {
        return $this->parser->extract($text);
    }

    public function detachAll(Post $post, ?bool $wasEligible = null): void
    {
        $hashtags = $post->hashtags()->pluck('hashtags.id')->all();
        $post->hashtags()->detach();

        $eligible = $wasEligible ?? $this->isEligibleForUsage($post);

        if (! $eligible) {
            return;
        }

        $this->decrementUsage($hashtags);
    }

    /**
     * @param  list<int>|null  $previousHashtagIds
     */
    public function syncUsageForEligibilityChange(
        Post $post,
        bool $wasEligible,
        bool $isEligible,
        ?array $previousHashtagIds = null
    ): void {
        if ($wasEligible === $isEligible) {
            return;
        }

        $hashtagIds = $isEligible
            ? $post->hashtags()->pluck('hashtags.id')->all()
            : ($previousHashtagIds ?? $post->hashtags()->pluck('hashtags.id')->all());

        if ($isEligible) {
            $this->incrementUsage($hashtagIds);
        } else {
            $this->decrementUsage($hashtagIds);
        }
    }

    public function isEligibleForUsage(Post $post): bool
    {
        $status = $post->status instanceof PostStatus
            ? $post->status->value
            : (string) $post->status;

        return $this->isEligibleForUsageState(
            $status,
            $post->published_at?->toDateTimeString(),
            $post->deleted_at?->toDateTimeString()
        );
    }

    public function isEligibleForUsageState(
        ?string $status,
        string|\DateTimeInterface|null $publishedAt,
        string|\DateTimeInterface|null $deletedAt
    ): bool {
        if ($deletedAt !== null) {
            return false;
        }

        if ($status !== PostStatus::Published->value) {
            return false;
        }

        if ($publishedAt === null || $publishedAt === '') {
            return true;
        }

        if ($publishedAt instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($publishedAt)->lessThanOrEqualTo(now());
        }

        return CarbonImmutable::parse($publishedAt)->lessThanOrEqualTo(now());
    }

    public function recalculateUsageCounts(): void
    {
        Hashtag::query()
            ->withCount([
                'posts as eligible_posts_count' => fn ($query) => $query
                    ->where('posts.status', PostStatus::Published->value)
                    ->whereNull('posts.deleted_at')
                    ->where(function ($publishedQuery): void {
                        $publishedQuery
                            ->whereNull('posts.published_at')
                            ->orWhere('posts.published_at', '<=', now());
                    }),
            ])
            ->chunkById(200, function (Collection $hashtags): void {
                foreach ($hashtags as $hashtag) {
                    $hashtag->updateQuietly([
                        'posts_count' => (int) ($hashtag->eligible_posts_count ?? 0),
                    ]);
                }
            });
    }

    /**
     * @return Collection<int, Hashtag>
     */
    public function relatedHashtags(Hashtag $hashtag, ?User $viewer, int $limit = 6): Collection
    {
        $postIds = Post::query()
            ->byTag($hashtag->slug)
            ->published()
            ->visibleTo($viewer)
            ->select(['posts.id'])
            ->limit(500)
            ->pluck('posts.id');

        if ($postIds->isEmpty()) {
            return Hashtag::query()->whereKey(-1)->get();
        }

        return Hashtag::query()
            ->whereKeyNot($hashtag->getKey())
            ->whereHas('posts', fn ($query) => $query->whereIn('posts.id', $postIds))
            ->withCount([
                'posts as cooccurrence_count' => fn ($query) => $query->whereIn('posts.id', $postIds),
            ])
            ->orderByDesc('cooccurrence_count')
            ->orderByDesc('posts_count')
            ->limit($limit)
            ->get();
    }

    private function resolveHashtagId(string $normalized): ?int
    {
        $normalized = $this->normalizer->normalize($normalized);

        if (! $normalized) {
            return null;
        }

        $hashtag = Hashtag::query()
            ->where('normalized_name', $normalized)
            ->orWhere('name', $normalized)
            ->first();

        if (! $hashtag) {
            $hashtag = Hashtag::query()->create([
                'name' => $normalized,
                'slug' => $normalized,
                'normalized_name' => $normalized,
            ]);

            return (int) $hashtag->getKey();
        }

        if ($hashtag->normalized_name !== $normalized || $hashtag->slug !== $normalized || $hashtag->name !== $normalized) {
            $hashtag->updateQuietly([
                'name' => $hashtag->name ?: $normalized,
                'slug' => $normalized,
                'normalized_name' => $normalized,
            ]);
        }

        return (int) $hashtag->getKey();
    }

    /**
     * @param  list<int>  $hashtagIds
     */
    private function incrementUsage(array $hashtagIds): void
    {
        if ($hashtagIds === []) {
            return;
        }

        Hashtag::query()
            ->whereIn('id', $hashtagIds)
            ->increment('posts_count');
    }

    /**
     * @param  list<int>  $hashtagIds
     */
    private function decrementUsage(array $hashtagIds): void
    {
        if ($hashtagIds === []) {
            return;
        }

        Hashtag::query()
            ->whereIn('id', $hashtagIds)
            ->where('posts_count', '>', 0)
            ->decrement('posts_count');
    }
}
