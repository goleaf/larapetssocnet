@props([
    'title' => null,
    'subtitle' => null,
    'description' => null,
    'icon' => null,
])

@php
    $subtitleText = $description ?? $subtitle;
    $actionSlot = $action ?? $actions ?? null;
@endphp

<div {{ $attributes->merge(['class' => 'mb-4 flex items-start justify-between gap-4 border-b border-whisker/40 pb-4']) }}>
    <div class="min-w-0">
        <div class="flex items-start gap-3">
            @if($icon)
                <div class="mt-0.5 shrink-0 text-2xl leading-none">{{ $icon }}</div>
            @endif

            <div class="min-w-0">
                @if(filled($title))
                    <h3 class="text-base font-semibold font-display text-bark">{{ $title }}</h3>
                @endif

                @if(filled($subtitleText))
                    <p class="mt-1 text-sm text-fur">{{ $subtitleText }}</p>
                @endif

                @if($slot->isNotEmpty())
                    <div class="mt-2 text-sm text-fur">
                        {{ $slot }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($actionSlot)
        <div class="shrink-0">
            {{ $actionSlot }}
        </div>
    @endif
</div>
