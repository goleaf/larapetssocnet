<?php

namespace App\Actions\Posts;

use App\Actions\Engagement\TrackShareAction;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CanonicalContentUrlService;
use App\Support\Posts\PostCreationInput;
use Illuminate\Support\Facades\Gate;

class CreateRepostAction
{
    public function __construct(
        private readonly CreatePostAction $posts,
        private readonly TrackShareAction $shares,
        private readonly CanonicalContentUrlService $urls,
    ) {}

    /**
     * @return array{post: Post, shares_count: int, original_url: string, repost_url: string}
     */
    public function handle(User $actor, Post $original): array
    {
        Gate::forUser($actor)->authorize('share', $original);

        $repost = $this->posts->handle($actor, PostCreationInput::fromUserInput($actor, [
            'body' => null,
            'visibility' => $this->defaultVisibility($actor),
            'original_post_id' => $original->getKey(),
        ]))->createdPost();

        $shareResult = $this->shares->handle($actor, $original, 'repost');

        if (! $shareResult['shared']) {
            $original->incrementCounter('shares_count');
        }

        return [
            'post' => $repost,
            'shares_count' => (int) ($original->fresh()?->shares_count ?? $original->shares_count ?? 0),
            'original_url' => $this->urls->post($original),
            'repost_url' => $this->urls->post($repost),
        ];
    }

    private function defaultVisibility(User $actor): string
    {
        return match ($actor->profile_visibility) {
            'private' => Post::VISIBILITY_PRIVATE,
            'followers_only' => Post::VISIBILITY_FOLLOWERS,
            default => Post::VISIBILITY_PUBLIC,
        };
    }
}
