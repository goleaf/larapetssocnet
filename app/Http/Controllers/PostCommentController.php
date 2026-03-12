<?php

namespace App\Http\Controllers;

use App\Actions\Comments\CreateCommentAction;
use App\Actions\Comments\DeleteCommentAction;
use App\Actions\Comments\UpdateCommentAction;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post, CreateCommentAction $action): RedirectResponse
    {
        $action->handle(
            $request->user(),
            $post,
            (string) $request->validated('body'),
            $request->validated('parent_id') ? (int) $request->validated('parent_id') : null,
        );

        return back()->with('status', 'Comment posted.');
    }

    public function update(UpdateCommentRequest $request, Post $post, Comment $comment, UpdateCommentAction $action): RedirectResponse
    {
        abort_unless((int) $comment->post_id === (int) $post->getKey(), 404);

        $action->handle($request->user(), $comment, (string) $request->validated('body'));

        return back()->with('status', 'Comment updated.');
    }

    public function destroy(Request $request, Post $post, Comment $comment, DeleteCommentAction $action): RedirectResponse
    {
        abort_unless((int) $comment->post_id === (int) $post->getKey(), 404);

        $action->handle($request->user(), $comment);

        return back()->with('status', 'Comment deleted.');
    }
}
