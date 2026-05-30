<?php

namespace App\Services;

use App\Models\Content\Comment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CommentQualityService
{
    public function score(string $body, int $reactionCount = 0): int
    {
        return $this->scoreParts($body, $reactionCount, 0, mentionCount: 0, createdAt: now(), hasPendingReport: false);
    }

    public function scoreComment(Comment $comment): int
    {
        $mentionCount = DB::table('comment_mentions')
            ->where('comment_id', $comment->getKey())
            ->count();
        $hasPendingReport = $comment->reports()
            ->pending()
            ->exists();

        return $this->scoreParts(
            (string) $comment->body,
            (int) $comment->paw_count,
            (int) $comment->love_count,
            $mentionCount,
            $comment->created_at,
            $hasPendingReport,
        );
    }

    public function scoreParts(
        string $body,
        int $pawCount,
        int $loveCount,
        int $mentionCount,
        ?Carbon $createdAt,
        bool $hasPendingReport,
    ): int {
        $body = trim($body);
        $length = mb_strlen($body);

        $score = match (true) {
            $length >= 151 => 3,
            $length >= 51 => 2,
            $length >= 20 => 1,
            default => 0,
        };

        $score += min(20, max(0, $pawCount) * 2 + max(0, $loveCount) * 3);

        if ($mentionCount > 0 || preg_match('/(^|\s)@[A-Za-z0-9-]{1,30}\b/', $body) === 1) {
            $score += 1;
        }

        if ($createdAt instanceof Carbon) {
            $score += match (true) {
                $createdAt->greaterThanOrEqualTo(now()->subDay()) => 3,
                $createdAt->greaterThanOrEqualTo(now()->subDays(7)) => 2,
                $createdAt->greaterThanOrEqualTo(now()->subDays(30)) => 1,
                default => 0,
            };
        }

        if ($hasPendingReport || $this->containsFlaggedWord($body)) {
            $score -= 3;
        }

        return max(0, $score);
    }

    public function refresh(Comment $comment): Comment
    {
        $comment->forceFill([
            'quality_score' => $this->scoreComment($comment),
        ])->save();

        return $comment->refresh();
    }

    private function containsFlaggedWord(string $body): bool
    {
        $normalized = mb_strtolower($body);

        foreach ((array) config('comments.flagged_words', []) as $word) {
            $word = trim(mb_strtolower((string) $word));

            if ($word !== '' && str_contains($normalized, $word)) {
                return true;
            }
        }

        return false;
    }
}
