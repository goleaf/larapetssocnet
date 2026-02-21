@props([
    'post',
    'showComments' => false,
    'compact' => false,
])

<article {{ $attributes->merge(['class' => 'shell-card overflow-hidden p-4 sm:p-5']) }}>
    <header class="flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <x-avatar :src="$post->author?->getFirstMediaUrl('avatar')" :name="$post->author?->name" size="md" />
            <div class="min-w-0">
                <p class="truncate shell-title text-sm">{{ $post->author?->name }}</p>
                <p class="truncate text-xs shell-text-muted">@{{ $post->author?->username }} · {{ optional($post->created_at)->diffForHumans() }}</p>
            </div>
        </div>

        @if ($post->is_pinned)
            <span class="chip">📌 Pinned post</span>
        @endif
    </header>

    @if ($post->body_html)
        <div class="prose prose-sm mt-4 max-w-none">
            {!! $post->body_html !!}
        </div>
    @endif

    @if ($post->type === \App\Models\Post::TYPE_PHOTO)
        <x-media-grid :post="$post" />
    @elseif ($post->type === \App\Models\Post::TYPE_VIDEO)
        <x-video-player :post="$post" />
    @endif

    @if ($post->location)
        <p class="mt-3 text-xs shell-text-muted">📍 {{ $post->location }}</p>
    @endif

    <x-reaction-bar
        class="mt-4"
        :likes="$post->likes_count"
        :comments="$post->comments_count"
        :shares="$post->shares_count"
        :saves="0"
        :reacted="false"
        :saved="false"
    />

    @if ($showComments)
        <x-comment-form class="mt-4" :action="route('posts.comments.store', $post)" compact />
    @endif
</article>
