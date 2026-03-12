<?php

namespace App\Http\Controllers;

use App\Actions\Comments\CreateCommentAction;
use App\Actions\Comments\DeleteCommentAction;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post, CreateCommentAction $action): RedirectResponse
    {
        $action->handle(
            $request->user(),
            $post,
            (string) $request->validated('body'),
            $request->validated('parent_id') ? (int) $request->validated('parent_id') : null,
        );

        return back()->with('success', 'Comment posted.');
    }

    public function destroy(Request $request, Comment $comment, DeleteCommentAction $action): RedirectResponse
    {
        $action->handle($request->user(), $comment);

        return back()->with('success', 'Comment deleted.');
    }
}
