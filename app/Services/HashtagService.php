<?php

namespace App\Services;

use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Support\Str;

class HashtagService
{
    public function __construct(private readonly CounterCacheService $counterCacheService) {}

    public function syncHashtags(Post $post): void
    {
        $tags = collect($this->extract((string) $post->body));

        $ids = $tags->map(function (string $tag): int {
            return (int) Hashtag::query()->firstOrCreate(
                ['name' => Str::lower($tag)],
                ['slug' => Str::slug($tag)]
            )->getKey();
        });

        $old = $post->hashtags()->pluck('hashtags.id');
        $attach = $ids->diff($old);
        $detach = $old->diff($ids);

        $post->hashtags()->sync($ids->all());

        Hashtag::query()->whereIn('id', $attach)->get()->each(fn (Hashtag $h) => $h->increment('posts_count'));
        Hashtag::query()->whereIn('id', $detach)->get()->each(fn (Hashtag $h) => $this->counterCacheService->safeDecrement($h, 'posts_count'));
    }

    /**
     * @return array<int, string>
     */
    public function extract(string $text): array
    {
        preg_match_all('/#([a-zA-Z0-9_]{1,50})/u', $text, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
    }

    public function detachAll(Post $post): void
    {
        $hashtags = $post->hashtags()->get();
        $post->hashtags()->detach();

        $hashtags->each(fn (Hashtag $hashtag) => $this->counterCacheService->safeDecrement($hashtag, 'posts_count'));
    }
}
