<?php

use App\Models\Content\Comment;
use App\Models\Content\Post;
use Illuminate\Support\Collection;
use Livewire\Component;

new class extends Component
{
    public Post $post;

    /**
     * @return Collection<int, Comment>
     */
    public function comments(): Collection
    {
        return Comment::query()
            ->threadColumns()
            ->topLevel()
            ->visibleTo(auth()->user())
            ->where('comments.post_id', $this->post->getKey())
            ->with(['user.media', 'replies.user.media'])
            ->oldest()
            ->limit(5)
            ->get();
    }
};
?>

<x-ui.card padding="base">
    @php($comments = $this->comments())

    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm font-bold text-bark">Comments</p>
        <a href="{{ route('posts.show', $post) }}#comments" class="text-xs font-semibold text-paw hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw">
            View full thread
        </a>
    </div>

    @forelse ($comments as $comment)
        @if ($loop->first)
            <div class="space-y-4">
        @endif
            <x-comment-item :comment="$comment" :post="$post"/>
        @if ($loop->last)
            </div>
        @endif
    @empty
        <p class="text-sm text-fur">No comments yet.</p>
    @endforelse
</x-ui.card>
