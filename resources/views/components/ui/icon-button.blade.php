@props([
    'variant' => 'ghost',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'icon' => null,
])
@php
    $baseClasses = 'inline-flex items-center justify-center rounded-pill aspect-square transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw';

    $variants = [
        'primary' => 'bg-paw text-white hover:bg-paw-dark shadow-button',
        'secondary' => 'bg-paw-light text-paw hover:bg-orange-200',
        'ghost' => 'bg-transparent text-fur hover:bg-cream border border-whisker/40',
        'danger' => 'bg-rose text-white hover:bg-red-700 shadow-button',
        'success' => 'bg-leaf text-white hover:bg-green-700 shadow-button',
        'outline' => 'border border-whisker text-bark bg-transparent hover:bg-cream',
    ];

    $sizes = [
        'xs' => 'w-6 h-6 text-xs',
        'sm' => 'w-8 h-8 text-sm',
        'md' => 'w-10 h-10 text-base',
        'lg' => 'w-12 h-12 text-lg',
    ];

    $classes = \Illuminate\Support\Arr::toCssClasses([
        $baseClasses,
        $variants[$variant] ?? $variants['ghost'],
        $sizes[$size] ?? $sizes['md'],
        'opacity-50 cursor-not-allowed pointer-events-none' => $disabled,
    ]);
@endphp

    @if($href)
        <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled) tabindex="-1" aria-disabled="true" @endif>
    @else
        <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled) disabled @endif>
    @endif
    @if($icon)
        {!! $icon !!}
    @else
        {{ $slot }}
    @endif
@if($href)
    </a>
@else
    </button>
@endif
