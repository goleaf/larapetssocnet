@props([
    'name' => 'Community Member',
    'headline' => null,
    'bio' => null,
    'followers' => null,
    'following' => false,
])

<article {{ $attributes->merge(['class' => 'shell-card p-4']) }}>
    <div class="flex items-start gap-3">
        <x-avatar :name="$name" size="md" />
        <div class="min-w-0">
            <h3 class="truncate shell-title text-base">{{ $name }}</h3>
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
</article>
