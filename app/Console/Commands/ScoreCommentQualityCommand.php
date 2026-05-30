<?php

namespace App\Console\Commands;

use App\Models\Content\Comment;
use App\Models\Content\Reaction;
use App\Models\Moderation\Report;
use App\Services\CommentQualityService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

#[Signature('comments:score-quality {--all : Recalculate every comment instead of recently touched comments}')]
#[Description('Score comment quality for Top sorting')]
class ScoreCommentQualityCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CommentQualityService $quality): int
    {
        $cacheKey = 'comments:quality:last-run';
        $lastRun = $this->option('all') ? null : Cache::get($cacheKey);
        $lastRunAt = is_string($lastRun) ? Carbon::parse($lastRun) : null;

        $query = Comment::withTrashed()->select(['id', 'body', 'paw_count', 'love_count', 'created_at']);

        if ($lastRunAt instanceof Carbon) {
            $commentIds = $this->changedCommentIds($lastRunAt);

            if ($commentIds === []) {
                Cache::put($cacheKey, now()->toIso8601String());
                $this->info('No comments required quality rescoring.');

                return self::SUCCESS;
            }

            $query->whereKey($commentIds);
        }

        $processed = 0;

        $query->orderBy('id')->chunkById(200, function ($comments) use ($quality, &$processed): void {
            $comments->each(function (Comment $comment) use ($quality, &$processed): void {
                $quality->refresh($comment);
                $processed++;
            });
        });

        Cache::put($cacheKey, now()->toIso8601String());
        $this->info("Scored {$processed} comments.");

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function changedCommentIds(Carbon $since): array
    {
        $commentIds = Comment::withTrashed()
            ->where('created_at', '>=', $since)
            ->orWhere('updated_at', '>=', $since)
            ->pluck('id');

        $reactionCommentIds = Reaction::query()
            ->where('reactable_type', (new Comment)->getMorphClass())
            ->where('updated_at', '>=', $since)
            ->pluck('reactable_id');

        $reportedCommentIds = Report::query()
            ->where('reportable_type', (new Comment)->getMorphClass())
            ->where('updated_at', '>=', $since)
            ->pluck('reportable_id');

        return $commentIds
            ->merge($reactionCommentIds)
            ->merge($reportedCommentIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
