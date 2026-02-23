@props([
    'name' => 'Community Member',
    'username' => null,
    'avatar' => null,
    'headline' => null,
    'bio' => null,
    'followers' => null,
    'following' => false,
    'profileHref' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

<article {{ $attributes->merge(['class' => 'shell-card hover-lift p-4 dark:border-slate-700/60 dark:bg-slate-900/40']) }}>
    <div class="flex items-start gap-3">
        <x-avatar :name="$name" :src="$avatar" size="md" :status="$following ? 'online' : null" />
        <div class="min-w-0">
            @if ($profileHref)
                <a href="{{ $profileHref }}" class="truncate shell-title text-base hover:underline" aria-label="Open {{ $name }} profile">
                    {{ $name }}
                </a>
            @else
                <h3 class="truncate shell-title text-base">{{ $name }}</h3>
            @endif
            @if ($username)
                <p class="truncate text-xs shell-text-muted">&#64;{{ $username }}</p>
            @elseif ($headline)
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

    @if ($actionLabel)
        @if ($actionHref)
            <a href="{{ $actionHref }}" class="btn-base btn-ghost mt-3 w-full justify-center text-xs" aria-label="{{ $actionLabel }} for {{ $name }}">
                {{ $actionLabel }}
            </a>
        @else
            <x-follow-button
                variant="{{ $following ? 'ghost' : 'primary' }}"
                size="sm"
                class="mt-3 w-full justify-center"
                :following="$following"
                aria-label="{{ $actionLabel }} for {{ $name }}"
            >
                {{ $actionLabel }}
            </x-follow-button>
        @endif
    @endif
</article>
