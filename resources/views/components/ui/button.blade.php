@props([
    'variant' => 'primary',
    'size' => 'md',
    'loading' => false,
    'disabled' => false,
    'loadingText' => null,
    'as' => null,
    'href' => null,
    'asLink' => false,
    'full' => false,
    'icon' => null,
])

@php
    $toBoolean = static function (mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $normalized ?? $value !== '';
        }

        return (bool) $value;
    };

    $isLoading = $toBoolean($loading);
    $isDisabled = $toBoolean($disabled) || $isLoading;
    $isFull = $toBoolean($full);
    $wantsLink = $toBoolean($asLink) || strtolower((string) $as) === 'a' || filled($href);
    $resolvedTag = $wantsLink ? 'a' : 'button';

    $variantClasses = [
        'primary'   => 'bg-paw text-white hover:bg-paw-dark shadow-button',
        'secondary' => 'bg-paw-light text-paw-dark hover:bg-orange-200',
        'ghost'     => 'bg-transparent text-fur hover:bg-cream',
        'danger'    => 'bg-rose text-white hover:bg-red-700',
        'success'   => 'bg-leaf text-white hover:bg-green-700',
        'outline'   => 'border border-whisker text-bark bg-transparent hover:bg-cream',
    ][$variant] ?? 'bg-paw text-white hover:bg-paw-dark shadow-button';

    $sizeClasses = [
        'xs' => 'px-2.5 py-1 text-xs rounded-sm',
        'sm' => 'px-3.5 py-1.5 text-sm rounded-md',
        'md' => 'px-5 py-2.5 text-sm rounded-md',
        'lg' => 'px-7 py-3.5 text-base rounded-md',
    ][$size] ?? 'px-5 py-2.5 text-sm rounded-md';

    $stateClasses = [
        'opacity-50 cursor-not-allowed pointer-events-none' => $isDisabled,
        'w-full justify-center' => $isFull,
    ];

    $hasDisabledBinding = $attributes->has(':disabled') || $attributes->has('x-bind:disabled');
    $hasAriaBusyBinding = $attributes->has(':aria-busy') || $attributes->has('x-bind:aria-busy') || $attributes->has('aria-busy');
    $hasAriaDisabledBinding = $attributes->has(':aria-disabled') || $attributes->has('x-bind:aria-disabled') || $attributes->has('aria-disabled');
    $resolvedHref = $isDisabled ? null : (filled($href) ? $href : '#');
@endphp

@if ($resolvedTag === 'a')
    <a
        {{ $attributes->class([
            'inline-flex items-center justify-center gap-2 font-medium transition-all duration-150',
            $variantClasses,
            $sizeClasses,
            ...$stateClasses,
        ]) }}
        @if (filled($resolvedHref))
            href="{{ $resolvedHref }}"
        @endif
        @if ($isDisabled && ! $hasAriaDisabledBinding)
            aria-disabled="true"
        @endif
        @if ($isLoading && ! $hasAriaBusyBinding)
            aria-busy="true"
        @endif
        @if ($isDisabled)
            tabindex="-1"
        @endif
    >
        @if ($isLoading)
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".2" />
                <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span>{{ $loadingText ?? $slot }}</span>
        @else
            @if ($icon)
                <span class="shrink-0" aria-hidden="true">{!! $icon !!}</span>
            @endif
            {{ $slot }}
        @endif
    </a>
@else
    <button
        {{ $attributes->class([
            'inline-flex items-center justify-center gap-2 font-medium transition-all duration-150',
            $variantClasses,
            $sizeClasses,
            ...$stateClasses,
        ])->merge(['type' => 'button']) }}
        @if (! $hasDisabledBinding)
            @disabled($isDisabled)
        @endif
        @if ($isLoading && ! $hasAriaBusyBinding)
            aria-busy="true"
        @endif
    >
        @if ($isLoading)
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".2" />
                <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
            </svg>
            <span>{{ $loadingText ?? $slot }}</span>
        @else
            @if ($icon)
                <span class="shrink-0" aria-hidden="true">{!! $icon !!}</span>
            @endif
            {{ $slot }}
        @endif
    </button>
@endif
