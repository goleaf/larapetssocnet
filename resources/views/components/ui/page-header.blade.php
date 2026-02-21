@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'icon' => '🐾',
])

<div {{ $attributes->merge(['class' => 'shell-panel p-4 sm:p-5']) }}>
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            @if ($eyebrow)
                <p class="shell-kicker">{{ $eyebrow }}</p>
            @endif

            <div class="mt-1 flex items-start gap-3">
                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-xl" style="background: color-mix(in srgb, var(--ui-primary) 16%, var(--ui-surface) 84%);">
                    {{ $icon }}
                </span>

                <div class="min-w-0">
                    <h1 class="shell-title text-2xl sm:text-[1.7rem]">{{ $title }}</h1>

                    @if ($description)
                        <p class="mt-1 text-sm shell-text-muted">{{ $description }}</p>
                    @endif
                </div>
            </div>
        </div>

        @if (isset($actions))
            <div class="flex flex-wrap items-center gap-2">
                {{ $actions }}
            </div>
        @endif
    </div>
</div>
