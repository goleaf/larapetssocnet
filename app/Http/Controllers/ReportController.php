<?php

namespace App\Http\Controllers;

use App\Actions\Engagement\CreateReportAction;
use App\Http\Requests\StoreGenericReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function __construct(private readonly CreateReportAction $createReport) {}

    public function store(StoreGenericReportRequest $request): JsonResponse
    {
        $viewer = $request->user();

        $reportable = $this->resolveReportable(
            $request->validated('reportable_type'),
            (int) $request->validated('reportable_id'),
            $viewer
        );

        $this->authorize('report', $reportable);

        try {
            $this->createReport->handle(
                $viewer,
                $reportable,
                (string) $request->validated('reason'),
                $request->validated('details')
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you. Your report has been received.',
        ]);
    }

    public function reportPost(StoreReportRequest $request, Post $post): RedirectResponse
    {
        $viewer = $request->user();
        $this->authorize('report', $post);

        abort_unless($post->canBeViewedBy($viewer), 403);

        try {
            $this->createReport->handle(
                $viewer,
                $post,
                (string) $request->string('reason'),
                $request->string('details')->toString() ?: null
            );
        } catch (ValidationException $exception) {
            return back()->withErrors(['report' => $exception->getMessage()]);
        }

        return back()->with('status', 'Post reported. Thank you.');
    }

    public function reportComment(StoreReportRequest $request, Post $post, Comment $comment): RedirectResponse
    {
        $viewer = $request->user();

        if ((int) $comment->post_id !== (int) $post->id) {
            abort(404);
        }

        abort_unless($post->canBeViewedBy($viewer), 403);

        $this->authorize('report', $comment);

        try {
            $this->createReport->handle(
                $viewer,
                $comment,
                (string) $request->string('reason'),
                $request->string('details')->toString() ?: null
            );
        } catch (ValidationException $exception) {
            return back()->withErrors(['report' => $exception->getMessage()]);
        }

        return back()->with('status', 'Comment reported. Thank you.');
    }

    public function reportUser(StoreReportRequest $request, User $user): RedirectResponse
    {
        $viewer = $request->user();

        if ((int) $viewer->id === (int) $user->id) {
            return back()->withErrors(['report' => 'You cannot report yourself.']);
        }

        $this->authorize('report', $user);

        try {
            $this->createReport->handle(
                $viewer,
                $user,
                (string) $request->string('reason'),
                $request->string('details')->toString() ?: null
            );
        } catch (ValidationException $exception) {
            return back()->withErrors(['report' => $exception->getMessage()]);
        }

        return back()->with('status', 'User reported. Thank you.');
    }

    private function resolveReportable(string $type, int $id, User $viewer): Post|Comment|User
    {
        return match ($type) {
            'post' => Post::query()
                ->visibleTo($viewer)
                ->findOrFail($id),
            'comment' => tap(Comment::query()->findOrFail($id), function (Comment $comment) use ($viewer): void {
                $post = $comment->post;
                abort_unless($post && $post->canBeViewedBy($viewer), 404);
            }),
            'user' => tap(User::query()->findOrFail($id), function (User $user) use ($viewer): void {
                abort_unless($user->canBeViewedBy($viewer), 404);
            }),
        };
    }
}
