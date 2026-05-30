<?php

namespace App\Services;

use App\Events\PostLinkPreviewFetched;
use App\Models\Content\Post;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PostLinkPreviewService
{
    public function __construct(private readonly PostMetadataService $metadata) {}

    public function fetch(string $url, ?int $postId = null, ?string $cacheKey = null): void
    {
        try {
            $this->fetchPreview($url, $postId, $cacheKey);
        } catch (Throwable $exception) {
            $this->markFailed($url, $cacheKey);

            report($exception);
        }
    }

    private function fetchPreview(string $url, ?int $postId = null, ?string $cacheKey = null): void
    {
        $preview = $this->metadata->fetchLinkPreview($url);

        if ($cacheKey !== null) {
            Cache::put($cacheKey, [
                'status' => $preview === null ? 'failed' : 'ready',
                'url' => $url,
                'preview' => $preview,
            ], now()->addMinutes(10));
        }

        if ($preview === null || $postId === null) {
            return;
        }

        $post = Post::query()->find($postId);

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

    private function markFailed(string $url, ?string $cacheKey = null): void
    {
        if ($cacheKey === null) {
            return;
        }

        Cache::put($cacheKey, [
            'status' => 'failed',
            'url' => $url,
            'preview' => null,
        ], now()->addMinutes(10));
    }
}
