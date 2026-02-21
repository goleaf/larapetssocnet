@props([
    'icon' => '🐾',
    'title' => 'Nothing found',
    'description' => 'Try adjusting filters or create a new item.',
])

<div {{ $attributes->merge(['class' => 'shell-card p-10 text-center']) }}>
    <div class="mx-auto inline-flex h-14 w-14 items-center justify-center rounded-2xl text-2xl" style="background: color-mix(in srgb, var(--ui-primary) 16%, var(--ui-surface) 84%);">
        {{ $icon }}
    </div>

    <h3 class="mt-4 shell-title text-xl">{{ $title }}</h3>
    <p class="mx-auto mt-2 max-w-lg text-sm shell-text-muted">{{ $description }}</p>

    @if (isset($actions))
        <div class="mt-5 flex justify-center gap-2">
            {{ $actions }}
        </div>
    @endif
</div>
