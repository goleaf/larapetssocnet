@props([
    'align' => 'right',
    'width' => '48',
    'contentClasses' => 'py-1',
])
@php
    $alignmentClasses = match ($align) {
        'left' => 'origin-top-left left-0',
        'top' => 'origin-top',
        default => 'origin-top-right right-0',
    };

    $widthClasses = match ($width) {
        'auto' => 'w-auto whitespace-nowrap',
        '56' => 'w-56',
        '64' => 'w-64',
        default => 'w-48',
    };
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open" class="inline-block cursor-pointer">
        {{ $trigger }}
</div>
<div x-show="open"
 x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
 x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $widthClasses }} {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="bg-warm-white rounded-lg shadow-card-hover border border-whisker/30 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
