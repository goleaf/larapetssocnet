<?php

namespace App\Services;

use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Support\Str;

class HashtagService
{
    public function syncHashtags(Post $post): void
    {
        $tags = $this->extract($post->body ?? '');

        $ids = collect($tags)->map(function ($tag) {
            return Hashtag::firstOrCreate(
                ['name' => strtolower($tag)],
                ['slug' => Str::slug($tag)]
            )->id;
        });

        $old = $post->hashtags()->pluck('hashtags.id');
        $attach = $ids->diff($old);
        $detach = $old->diff($ids);

        $post->hashtags()->sync($ids);

        if ($attach->isNotEmpty()) {
            Hashtag::whereIn('id', $attach)->increment('posts_count');
        }

        if ($detach->isNotEmpty()) {
            Hashtag::whereIn('id', $detach)->decrement('posts_count');
        }
    }

    public function extract(string $text): array
    {
        preg_match_all('/#([a-zA-Z0-9_]{1,50})/u', $text, $m);

        return array_unique(array_map('strtolower', $m[1] ?? []));
    }

    public function detachAll(Post $post): void
    {
        $hashtags = $post->hashtags()->get();
        $post->hashtags()->detach();

        $hashtags->each(function ($h) {
            $h->decrement('posts_count');
        });
    }
}
