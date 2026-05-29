<?php

namespace App\Jobs;

use App\Events\PostLinkPreviewFetched;
use App\Models\Content\Post;
use App\Services\PostMetadataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class FetchLinkPreviewMetadataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly string $url,
        public readonly ?int $postId = null,
        public readonly ?string $cacheKey = null,
    ) {
        $this->afterCommit();
    }

    public function handle(PostMetadataService $metadata): void
    {
        $preview = $metadata->fetchLinkPreview($this->url);

        if ($this->cacheKey !== null) {
            Cache::put($this->cacheKey, [
                'status' => $preview === null ? 'failed' : 'ready',
                'url' => $this->url,
                'preview' => $preview,
            ], now()->addMinutes(10));
        }

        if ($preview === null || $this->postId === null) {
            return;
        }

        $post = Post::query()->find($this->postId);

        if (! $post instanceof Post) {
            return;
        }

        $currentPreview = $post->getAttribute('link_preview');

        if ((is_array($currentPreview) && $currentPreview !== []) || (is_string($currentPreview) && $currentPreview !== '')) {
            return;
        }

        $post->forceFill(['link_preview' => $preview])->save();

        PostLinkPreviewFetched::dispatch((int) $post->getKey(), $preview);
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function failed(?Throwable $exception): void
    {
        if ($this->cacheKey === null) {
            return;
        }

        Cache::put($this->cacheKey, [
            'status' => 'failed',
            'url' => $this->url,
            'preview' => null,
        ], now()->addMinutes(10));
    }
}
