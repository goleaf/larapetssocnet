@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
])

<div {{ $attributes->class(['flex items-center justify-between border-b border-whisker/30 pb-4 mb-4']) }}>
    <div class="flex items-center gap-3">
        @if (filled($icon))
            <span class="text-xl" aria-hidden="true">{{ $icon }}</span>
        @endif
        <div>
            @if (filled($title))
                <h3 class="text-base font-semibold font-display text-bark">{{ $title }}</h3>
            @endif
            @if (filled($subtitle))
                <p class="text-sm text-fur">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    @if (isset($action))
        <div class="shrink-0">
            {{ $action }}
        </div>
    @endif
</div>
