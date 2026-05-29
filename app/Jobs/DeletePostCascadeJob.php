<?php

namespace App\Jobs;

use App\Models\Content\Post;
use App\Models\Pets\Pet;
use App\Services\HashtagService;
use Closure;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

#[Backoff(1, 5, 10)]
class DeletePostCascadeJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $postId,
        public ?int $actorId = null,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->postId;
    }

    public function handle(HashtagService $hashtags): void
    {
        $lock = Cache::lock($this->lockKey(), 120);

        if (! $lock->get()) {
            Log::info('Post deletion cascade already locked.', $this->logContext(['step' => 'lock_skipped']));

            return;
        }

        try {
            $post = Post::withTrashed()
                ->with(['pets:id'])
                ->whereKey($this->postId)
                ->first();

            if (! $post instanceof Post) {
                $this->runStep('post_missing', function (): void {});

                return;
            }

            $legacyPetId = $post->pet_id ? (int) $post->pet_id : null;
            $taggedPetIds = $post->pets()
                ->pluck('pets.id')
                ->map(fn (mixed $petId): int => (int) $petId)
                ->all();
            $wasHashtagEligible = $hashtags->isEligibleForUsageState(
                (string) ($post->getRawOriginal('status') ?? $post->status?->value ?? $post->status),
                $post->getRawOriginal('published_at') ?? $post->published_at,
                null,
            );

            $this->runStep('soft_delete_post', function () use ($post): void {
                if (! $post->trashed()) {
                    $post->delete();
                }
            });

            $this->runStep('decrement_tagged_pet_counters', function () use ($taggedPetIds, $legacyPetId): void {
                $petIds = array_values(array_unique(array_filter(
                    $taggedPetIds,
                    fn (int $petId): bool => $legacyPetId === null || $petId !== $legacyPetId,
                )));

                if ($petIds === []) {
                    return;
                }

                Pet::query()
                    ->whereIn('id', $petIds)
                    ->where('posts_count', '>', 0)
                    ->decrement('posts_count');
            });

            $this->runStep('decrement_hashtag_counters', function () use ($post, $hashtags, $wasHashtagEligible): void {
                if (! $post->hashtags()->exists()) {
                    return;
                }

                $hashtags->detachAll($post, $wasHashtagEligible);
            });

            $this->runStep('remove_feed_items', function (): void {
                if (! Schema::hasTable('feed_items')) {
                    return;
                }

                DB::table('feed_items')
                    ->where('post_id', $this->postId)
                    ->delete();
            });

            $this->runStep('preserve_saved_placeholders', function (): void {
                if (! Schema::hasTable('saved_posts')) {
                    return;
                }

                $savedCount = DB::table('saved_posts')
                    ->where('post_id', $this->postId)
                    ->count();

                Log::info(
                    'Post deletion cascade preserved saved rows for deleted placeholders.',
                    $this->logContext(['saved_rows' => $savedCount])
                );
            });
        } finally {
            $lock->release();
        }
    }

    private function runStep(string $step, Closure $callback): void
    {
        $completed = $this->completedSteps();

        if (in_array($step, $completed, true)) {
            Log::info('Post deletion cascade step already completed.', $this->logContext(['step' => $step]));

            return;
        }

        $callback();

        $completed[] = $step;
        Cache::put($this->progressKey(), array_values(array_unique($completed)), now()->addDays(7));

        Log::info('Post deletion cascade step completed.', $this->logContext(['step' => $step]));
    }

    /**
     * @return list<string>
     */
    private function completedSteps(): array
    {
        $steps = Cache::get($this->progressKey(), []);

        if (! is_array($steps)) {
            return [];
        }

        return array_values(array_filter($steps, 'is_string'));
    }

    private function lockKey(): string
    {
        return 'posts:delete-cascade:'.$this->postId;
    }

    private function progressKey(): string
    {
        return 'posts:delete-cascade-progress:'.$this->postId;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function logContext(array $context = []): array
    {
        return array_merge([
            'post_id' => $this->postId,
            'actor_id' => $this->actorId,
        ], $context);
    }
}
