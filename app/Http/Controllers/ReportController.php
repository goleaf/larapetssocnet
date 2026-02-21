<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class ReportController extends Controller
{
    public function reportPost(StoreReportRequest $request, Post $post): RedirectResponse
    {
        $viewer = $request->user();
        $this->authorize('report', $post);

        abort_unless($post->canBeViewedBy($viewer), 403);

        return $this->upsertReport(
            reporter: $viewer,
            reportableType: Post::class,
            reportableId: (int) $post->id,
            reason: (string) $request->string('reason'),
            details: $request->string('details')->toString() ?: null,
            successMessage: 'Post reported. Thank you.'
        );
    }

    public function reportComment(StoreReportRequest $request, Post $post, Comment $comment): RedirectResponse
    {
        $viewer = $request->user();

        if ((int) $comment->post_id !== (int) $post->id) {
            abort(404);
        }

        abort_unless($post->canBeViewedBy($viewer), 403);

        if ((int) $comment->user_id === (int) $viewer->id) {
            return back()->withErrors(['report' => 'You cannot report your own comment.']);
        }

        return $this->upsertReport(
            reporter: $viewer,
            reportableType: Comment::class,
            reportableId: (int) $comment->id,
            reason: (string) $request->string('reason'),
            details: $request->string('details')->toString() ?: null,
            successMessage: 'Comment reported. Thank you.'
        );
    }

    public function reportUser(StoreReportRequest $request, User $user): RedirectResponse
    {
        $viewer = $request->user();

        if ((int) $viewer->id === (int) $user->id) {
            return back()->withErrors(['report' => 'You cannot report yourself.']);
        }

        return $this->upsertReport(
            reporter: $viewer,
            reportableType: User::class,
            reportableId: (int) $user->id,
            reason: (string) $request->string('reason'),
            details: $request->string('details')->toString() ?: null,
            successMessage: 'User reported. Thank you.'
        );
    }

    private function upsertReport(
        User $reporter,
        string $reportableType,
        int $reportableId,
        string $reason,
        ?string $details,
        string $successMessage
    ): RedirectResponse {
        Report::query()->updateOrCreate(
            [
                'reporter_user_id' => $reporter->id,
                'reportable_type' => $reportableType,
                'reportable_id' => $reportableId,
            ],
            [
                'reason' => $reason,
                'details' => $details,
                'status' => Report::STATUS_PENDING,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
            ]
        );

        return back()->with('status', $successMessage);
    }
}

