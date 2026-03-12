<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use RuntimeException;

class ModerationService
{
    public function deletePost(Post $post, User $moderator): void
    {
        $post->delete();
    }

    public function deleteComment(Comment $comment, User $moderator): void
    {
        app(CommentService::class)->delete($comment);
    }

    public function resolveReport(Report $report, User $admin, string $status, ?string $note = null): void
    {
        if (! in_array($status, ['reviewed', 'dismissed', 'actioned'], true)) {
            throw new RuntimeException("Invalid report status: {$status}");
        }

        $report->update([
            'status' => $status,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'resolution_notes' => $note,
        ]);
    }
}
