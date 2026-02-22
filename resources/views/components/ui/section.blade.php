@props([
    'title' => null,
    'subtitle' => null,
    'tight' => false,
])

<div {{ $attributes->class([
    $tight ? 'mb-4' : 'mb-6',
]) }}>
    <div class="flex items-center justify-between">
        <div>
            @if (filled($title))
                <h2 class="text-2xl font-bold font-display text-bark">{{ $title }}</h2>
            @endif
            @if (filled($subtitle))
                <p class="text-sm text-fur mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        @if (isset($action))
            <div class="shrink-0">
                {{ $action }}
            </div>
        @endif
    </div>
</div>
