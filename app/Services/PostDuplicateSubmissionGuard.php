<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Support\Posts\PostContentHasher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PostDuplicateSubmissionGuard
{
    public function __construct(private readonly PostContentHasher $hasher) {}

    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function run(User $user, ?string $body, callable $callback): mixed
    {
        $normalized = $this->hasher->normalized($body);

        if ($normalized === '') {
            return $callback();
        }

        $lock = Cache::lock($this->lockKey($user, $normalized), 10);

        if (! $lock->get()) {
            throw ValidationException::withMessages([
                'body' => 'This post is already being submitted. Please wait a moment before trying again.',
            ]);
        }

        try {
            $this->ensureNoRecentDuplicate($user, $normalized);

            return $callback();
        } finally {
            $lock->release();
        }
    }

    public function ensureAllowed(User $user, ?string $body): void
    {
        $normalized = $this->hasher->normalized($body);

        if ($normalized === '') {
            return;
        }

        $this->ensureNoRecentDuplicate($user, $normalized);
    }

    public function hash(?string $body): ?string
    {
        return $this->hasher->hash($body);
    }

    public function recentDuplicate(User $user, ?string $body): ?Post
    {
        $hash = $this->hash($body);

        if ($hash === null) {
            return null;
        }

        return Post::query()
            ->where('author_type', $user::class)
            ->where('author_id', $user->getKey())
            ->where('content_hash', $hash)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDay())
            ->latest('created_at')
            ->first();
    }

    private function lockKey(User $user, string $body): string
    {
        return 'posts:duplicate-submission:'.$user->getKey().':'.sha1($body);
    }

    private function ensureNoRecentDuplicate(User $user, string $body): void
    {
        $exists = Post::query()
            ->where('author_type', $user::class)
            ->where('author_id', $user->getKey())
            ->where('content_hash', hash('sha256', $body))
            ->whereNull('deleted_at')
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'body' => 'You already posted this recently.',
            ]);
        }
    }
}
