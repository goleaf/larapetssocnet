@props([
    'text' => '',
    'position' => 'top',
])

@php
    $positionClasses = [
        'top'    => 'bottom-full left-1/2 -translate-x-1/2 mb-2',
        'bottom' => 'top-full left-1/2 -translate-x-1/2 mt-2',
        'left'   => 'right-full top-1/2 -translate-y-1/2 mr-2',
        'right'  => 'left-full top-1/2 -translate-y-1/2 ml-2',
    ][$position] ?? 'bottom-full left-1/2 -translate-x-1/2 mb-2';

    $arrowClasses = [
        'top'    => 'top-full left-1/2 -translate-x-1/2 border-t-bark border-x-transparent border-b-transparent',
        'bottom' => 'bottom-full left-1/2 -translate-x-1/2 border-b-bark border-x-transparent border-t-transparent',
        'left'   => 'left-full top-1/2 -translate-y-1/2 border-l-bark border-y-transparent border-r-transparent',
        'right'  => 'right-full top-1/2 -translate-y-1/2 border-r-bark border-y-transparent border-l-transparent',
    ][$position] ?? 'top-full left-1/2 -translate-x-1/2 border-t-bark border-x-transparent border-b-transparent';
@endphp

<div
    x-data="{ hover: false }"
    @mouseenter="hover = true"
    @mouseleave="hover = false"
    {{ $attributes->class(['relative inline-flex']) }}
>
    {{ $slot }}

    <div
        x-show="hover"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-cloak
        class="absolute z-50 {{ $positionClasses }} pointer-events-none"
    >
        <div class="bg-bark text-cream text-xs px-2.5 py-1.5 rounded-md whitespace-nowrap font-medium shadow-lg">
            {{ $text }}
        </div>
        <span class="absolute border-4 {{ $arrowClasses }}"></span>
    </div>
</div>
