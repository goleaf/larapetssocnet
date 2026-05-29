<?php

namespace App\Services;

use App\Models\Content\Post;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PostDuplicateSubmissionGuard
{
    /**
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function run(User $user, ?string $body, callable $callback): mixed
    {
        $normalized = $this->normalize($body);

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
        $normalized = $this->normalize($body);

        if ($normalized === '') {
            return;
        }

        $this->ensureNoRecentDuplicate($user, $normalized);
    }

    private function normalize(?string $body): string
    {
        return trim((string) $body);
    }

    private function lockKey(User $user, string $body): string
    {
        return 'posts:duplicate-submission:'.$user->getKey().':'.sha1($body);
    }

    private function ensureNoRecentDuplicate(User $user, string $body): void
    {
        $exists = Post::query()
            ->where('user_id', $user->getKey())
            ->where('body', $body)
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
