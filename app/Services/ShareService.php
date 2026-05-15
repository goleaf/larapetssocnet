<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Content\Share;
use App\Models\Identity\User;
use Illuminate\Support\Facades\DB;

class ShareService
{
    public function __construct(private readonly CanonicalContentUrlService $canonicalUrls) {}

    /**
     * @return array{shared: bool, shares_count: int, url: string}
     */
    public function track(User $user, Post $post, string $method): array
    {
        $method = strtolower(trim($method)) ?: 'copy_link';

        $created = DB::transaction(function () use ($user, $post, $method): bool {
            $share = Share::query()->firstOrCreate([
                'user_id' => $user->id,
                'shareable_type' => $post->getMorphClass(),
                'shareable_id' => $post->id,
            ], [
                'method' => $method,
            ]);

            if (! $share->wasRecentlyCreated) {
                return false;
            }

            $post->incrementCounter('shares_count');

            return true;
        });

        $sharesCount = (int) ($post->fresh()?->shares_count ?? $post->shares_count ?? 0);

        return [
            'shared' => $created,
            'shares_count' => $sharesCount,
            'url' => $this->canonicalUrls->post($post),
        ];
    }
}
