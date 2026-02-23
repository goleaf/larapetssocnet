@props([
    'title' => null,
    'subtitle' => null,
    'description' => null,
    'breadcrumbs' => [],
    'eyebrow' => null,
    'icon' => null,
])

@php
    $summary = $description ?? $subtitle;
    $actionSlot = $action ?? $actions ?? null;
@endphp

<div {{ $attributes->merge(['class' => 'mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-end']) }}>
    <div class="min-w-0">
        @if(!empty($breadcrumbs))
            <x-ui.breadcrumbs :items="$breadcrumbs" class="mb-3" />
        @endif

        @if(filled($eyebrow))
            <p class="text-xs font-semibold uppercase tracking-[0.12em] text-fur">{{ $eyebrow }}</p>
        @endif

        <div class="mt-1 flex items-center gap-2">
            @if(filled($icon))
                <span class="text-2xl leading-none" aria-hidden="true">{{ $icon }}</span>
            @endif

            @if(filled($title))
                <h1 class="truncate text-3xl font-bold font-display text-bark">{{ $title }}</h1>
            @endif
        </div>

        @if(filled($summary))
            <p class="mt-1 text-sm text-fur">{{ $summary }}</p>
        @endif
    </div>

    @if($actionSlot)
        <div class="shrink-0 sm:mb-1">
            {{ $actionSlot }}
        </div>
    @endif
</div>
