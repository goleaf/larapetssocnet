@props([
    'name' => 'Community Member',
    'headline' => null,
    'bio' => null,
    'followers' => null,
    'following' => false,
    'profileHref' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<article {{ $attributes->merge(['class' => 'shell-card hover-lift p-4']) }}>
    <div class="flex items-start gap-3">
        <x-avatar :name="$name" size="md" :status="$following ? 'online' : null" />
        <div class="min-w-0">
            @if ($profileHref)
                <a href="{{ $profileHref }}" class="truncate shell-title text-base hover:underline">{{ $name }}</a>
            @else
                <h3 class="truncate shell-title text-base">{{ $name }}</h3>
            @endif
            @if ($headline)
                <p class="truncate text-xs shell-text-muted">{{ $headline }}</p>
            @endif
        </div>
    </div>

    @if ($bio)
        <p class="mt-3 text-sm shell-text-muted">{{ $bio }}</p>
    @endif

    @if ($followers)
        <p class="mt-3 text-xs shell-text-muted">{{ $followers }} followers</p>
    @endif

    @if ($following)
        <span class="chip mt-3">Following</span>
    @endif

    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="btn-base btn-ghost mt-3 w-full justify-center text-xs">
            {{ $actionLabel }}
        </a>
    @endif
</article>
