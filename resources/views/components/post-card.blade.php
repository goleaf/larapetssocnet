@props([
    'userName' => 'Pet Lover',
    'userHandle' => '@petlover',
    'userAvatar' => null,
    'postedAt' => 'Just now',
    'content' => null,
    'image' => null,
    'likes' => 0,
    'comments' => 0,
    'shares' => 0,
    'saves' => 0,
    'reacted' => false,
    'saved' => false,
    'commentAction' => '#',
    'showCommentForm' => false,
])

@php
    $body = $content ?? trim((string) $slot);
@endphp

<article {{ $attributes->merge(['class' => 'shell-card overflow-hidden p-4 sm:p-5']) }}>
    <header class="flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <x-avatar :src="$userAvatar" :name="$userName" size="md" />

            <div class="min-w-0">
                <p class="truncate shell-title text-sm">{{ $userName }}</p>
                <p class="truncate text-xs shell-text-muted">{{ $userHandle }} · {{ $postedAt }}</p>
            </div>
        </div>

        <button type="button" class="btn-base btn-ghost px-2 py-1.5 text-xs" aria-label="Post actions">
            •••
        </button>
    </header>

    @if ($body)
        <div class="mt-4 text-sm leading-6" style="color: var(--ui-text);">
            {{ $body }}
        </div>
    @endif

    @if ($image)
        <div class="mt-4 overflow-hidden rounded-xl border" style="border-color: var(--ui-border);">
            <img src="{{ $image }}" alt="Post media" class="h-auto w-full object-cover" loading="lazy">
        </div>
    @endif

    <x-reaction-bar
        class="mt-4"
        :likes="$likes"
        :comments="$comments"
        :shares="$shares"
        :saves="$saves"
        :reacted="$reacted"
        :saved="$saved"
    />

    @if ($showCommentForm)
        <x-comment-form class="mt-4" :action="$commentAction" compact />
    @endif
</article>
