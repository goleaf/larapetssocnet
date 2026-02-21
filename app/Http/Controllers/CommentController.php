<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        $this->authorize('view', $post);

        $validated = $request->validated();
        $parentId = $validated['parent_id'] ?? null;

        if ($parentId !== null) {
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

        return back()->with('success', 'Comment posted.');
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        $post = $comment->post;
        $commentIds = [$comment->id];

        if ($comment->parent_id === null) {
            $replyIds = Comment::query()
                ->where('post_id', $post->id)
                ->where('parent_id', $comment->id)
                ->pluck('id')
                ->all();

            $commentIds = array_merge($commentIds, $replyIds);
        }

        Comment::query()
            ->whereIn('id', $commentIds)
            ->delete();

        $post->refreshCommentsCount();

        return back()->with('success', 'Comment deleted.');
    }
}
