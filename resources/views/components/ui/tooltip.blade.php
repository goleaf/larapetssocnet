@props([
    'text',
    'position' => 'top',
])

@php
    $positionClasses = match ($position) {
        'bottom' => 'top-full left-1/2 -translate-x-1/2 mt-2',
        'left' => 'right-full top-1/2 -translate-y-1/2 mr-2',
        'right' => 'left-full top-1/2 -translate-y-1/2 ml-2',
        default => 'bottom-full left-1/2 -translate-x-1/2 mb-2',
    };
    
    $arrowClasses = match ($position) {
        'bottom' => 'bottom-full left-1/2 -translate-x-1/2 border-b-bark border-x-transparent border-t-transparent border-[6px]',
        'left' => 'left-full top-1/2 -translate-y-1/2 border-l-bark border-y-transparent border-r-transparent border-[6px]',
        'right' => 'right-full top-1/2 -translate-y-1/2 border-r-bark border-y-transparent border-l-transparent border-[6px]',
        default => 'top-full left-1/2 -translate-x-1/2 border-t-bark border-x-transparent border-b-transparent border-[6px]',
    };
@endphp

<div x-data="{ hover: false }" class="relative inline-block" @mouseenter="hover = true" @mouseleave="hover = false">
    {{ $slot }}
    
    <div 
        x-show="hover" 
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute z-50 {{ $positionClasses }} pointer-events-none whitespace-nowrap"
        style="display: none;"
    >
        <div class="bg-bark text-cream text-xs px-2.5 py-1.5 rounded-md shadow-md font-medium">
            {{ $text }}
        </div>
        <div class="absolute w-0 h-0 {{ $arrowClasses }}"></div>
    </div>
</div>
