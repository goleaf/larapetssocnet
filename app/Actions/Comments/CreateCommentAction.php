<?php

namespace App\Actions\Comments;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\CommentService;
use App\Services\ContentService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CreateCommentAction
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly ContentService $content,
    ) {}

    /**
     * @param  array{gif_url?: string|null, gif_preview_url?: string|null, gif_title?: string|null, gif_provider?: string|null}|null  $gif
     */
    public function handle(User $actor, Post|CreateCommentData $postOrData, ?string $body = null, ?int $parentId = null, ?array $gif = null): Comment
    {
        $data = $postOrData instanceof CreateCommentData
            ? $postOrData
            : new CreateCommentData($postOrData, (string) $body, $parentId, $gif);
        $post = $data->post;

        Gate::forUser($actor)->authorize('create', [Comment::class, $post]);

        $this->enforceRateLimit($actor);
        $this->rejectRecentDuplicate($actor, $data->body);

        $parent = null;

        if ($data->parentId !== null) {
            $parent = Comment::query()->whereKey($data->parentId)->firstOrFail();
            Gate::forUser($actor)->authorize('reply', $parent);
        }

        return $this->comments->create($post, $actor, $data->body, $parent, $data->gif);
    }

    private function enforceRateLimit(User $actor): void
    {
        $key = 'comments:create:'.$actor->getKey();

        if (RateLimiter::tooManyAttempts($key, 30)) {
            throw ValidationException::withMessages([
                'body' => 'You are commenting too quickly. Please try again later.',
            ]);
        }

        RateLimiter::hit($key, 3600);
    }

    private function rejectRecentDuplicate(User $actor, string $body): void
    {
        $body = $this->content->plainText($body) ?? '';

        if ($body === '') {
            return;
        }

        $latest = Comment::query()
            ->where('user_id', $actor->getKey())
            ->latest('created_at')
            ->first(['id', 'body', 'created_at']);

        if (! $latest instanceof Comment || $latest->created_at?->lessThan(now()->subMinutes(10))) {
            return;
        }

        if ((string) $latest->body !== $body) {
            return;
        }

        throw ValidationException::withMessages([
            'body' => 'Duplicate comment detected.',
        ]);
    }
}
