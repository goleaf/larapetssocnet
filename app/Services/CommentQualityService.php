<?php

namespace App\Services;

use App\Models\Content\Comment;

class CommentQualityService
{
    public function score(string $body, int $reactionCount = 0): int
    {
        $body = trim($body);
        $length = mb_strlen($body);

        $score = min(40, (int) floor($length / 8));
        $score += min(30, max(0, $reactionCount) * 3);

        if (preg_match('/(^|\s)@[A-Za-z0-9-]{1,30}\b/', $body) === 1) {
            $score += 15;
        }

        if ($this->containsFlaggedWord($body)) {
            $score -= 40;
        }

        return max(0, min(100, $score));
    }

    public function refresh(Comment $comment): Comment
    {
        $comment->forceFill([
            'quality_score' => $this->score((string) $comment->body, (int) $comment->reactions_count),
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
