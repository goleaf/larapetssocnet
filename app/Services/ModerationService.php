<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
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
