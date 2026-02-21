<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostCommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        abort_unless($post->canBeViewedBy($request->user()), 403);

        $validated = $request->validated();
        $parentId = $validated['parent_id'] ?? null;

        if ($parentId) {
            $parentComment = Comment::query()
                ->where('post_id', $post->id)
                ->whereKey($parentId)
                ->firstOrFail();

            if ($parentComment->parent_id !== null) {
                return back()
                    ->withErrors(['parent_id' => 'Only one reply level is allowed.'])
                    ->withInput();
            }
        }

        Comment::query()->create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'body' => $validated['body'],
        ]);

        $post->refreshCommentsCount();

        return back()->with('status', 'Comment posted.');
    }

    public function update(UpdateCommentRequest $request, Post $post, Comment $comment): RedirectResponse
    {
        abort_unless($comment->post_id === $post->id, 404);
        abort_unless($comment->user_id === $request->user()->id, 403);

        $comment->update([
            'body' => $request->validated('body'),
        ]);

        return back()->with('status', 'Comment updated.');
    }

    public function destroy(Request $request, Post $post, Comment $comment): RedirectResponse
    {
        abort_unless($comment->post_id === $post->id, 404);
        abort_unless($comment->user_id === $request->user()->id, 403);

        $comment->delete();
        $post->refreshCommentsCount();

        return back()->with('status', 'Comment deleted.');
    }
}
