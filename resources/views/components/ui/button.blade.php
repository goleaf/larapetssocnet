@props([
    'variant' => 'primary',
    'size' => 'md',
    'loading' => false,
    'disabled' => false,
    'loadingText' => 'Loading...',
    'as' => null,
    'href' => null,
    'asLink' => false,
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
    $wantsLink = $toBoolean($asLink) || strtolower((string) $as) === 'a' || filled($href);
    $resolvedTag = $wantsLink ? 'a' : 'button';

    $variantClasses = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'ghost' => 'btn-ghost',
        'danger' => 'btn-danger',
    ][$variant] ?? 'btn-primary';

    $sizeClasses = [
        'xs' => 'px-2.5 py-1.5 text-xs',
        'sm' => 'px-3 py-2 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
        'lg' => 'px-4 py-2.5 text-sm',
    ][$size] ?? 'px-3.5 py-2 text-sm';

    $stateClasses = [
        'pointer-events-none opacity-60' => $isDisabled,
    ];

    $hasDisabledBinding = $attributes->has(':disabled') || $attributes->has('x-bind:disabled');
    $hasAriaBusyBinding = $attributes->has(':aria-busy') || $attributes->has('x-bind:aria-busy') || $attributes->has('aria-busy');
    $hasAriaDisabledBinding = $attributes->has(':aria-disabled') || $attributes->has('x-bind:aria-disabled') || $attributes->has('aria-disabled');
    $resolvedHref = $isDisabled ? null : (filled($href) ? $href : '#');
@endphp

@if ($resolvedTag === 'a')
    <a
        {{ $attributes->class([
            'btn-base',
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
            <span>{{ $loadingText }}</span>
        @else
            {{ $slot }}
        @endif
    </a>
@else
    <button
        {{ $attributes->class([
            'btn-base',
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
            <span>{{ $loadingText }}</span>
        @else
            {{ $slot }}
        @endif
    </button>
@endif
