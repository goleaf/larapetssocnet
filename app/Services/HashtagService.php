<?php

namespace App\Services;

use App\Models\Hashtag;
use App\Models\Post;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HashtagService
{
    public function extractAndSyncHashtags(Post $post, string $body): void
    {
        preg_match_all('/#([\pL\pN_]+)/u', $body, $matches);

        $names = collect($matches[1] ?? [])
            ->map(static fn (string $name): string => Str::of($name)->lower()->trim()->toString())
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            $post->hashtags()->detach();
            $this->refreshCounts();

            return;
        }

        $ids = $names
            ->map(function (string $name): int {
                return (int) Hashtag::query()->firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name]
                )->getKey();
            })
            ->all();

        $post->hashtags()->sync($ids);
        $this->refreshCounts();
    }

    /**
     * @return Collection<int, string>
     */
    public function extract(string $body): Collection
    {
        preg_match_all('/#([\pL\pN_]+)/u', $body, $matches);

        return collect($matches[1] ?? [])
            ->map(static fn (string $name): string => Str::of($name)->lower()->trim()->toString())
            ->filter()
            ->unique()
            ->values();
    }

    public function refreshCounts(): void
    {
        DB::statement('UPDATE hashtags SET posts_count = (SELECT COUNT(*) FROM post_hashtag WHERE post_hashtag.hashtag_id = hashtags.id)');
    }
}
