<?php

namespace App\Http\Controllers\Posts;

use App\Actions\Comments\CreateCommentAction;
use App\Actions\Comments\DeleteCommentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Posts\StoreCommentRequest;
use App\Models\Content\Comment;
use App\Models\Content\Post;
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
