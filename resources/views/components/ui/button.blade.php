@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'button',
    'disabled' => false,
    'icon' => null,
    'loading' => false,
    'full' => false,
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium transition-all duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw';
    
    $variants = [
        'primary' => 'bg-paw text-white hover:bg-paw-dark shadow-button',
        'secondary' => 'bg-paw-light text-paw hover:bg-orange-200',
        'ghost' => 'bg-transparent text-fur hover:bg-cream border border-whisker/40',
        'danger' => 'bg-rose text-white hover:bg-red-700 shadow-button',
        'success' => 'bg-leaf text-white hover:bg-green-700 shadow-button',
        'outline' => 'border border-whisker text-bark bg-transparent hover:bg-cream',
    ];

    $sizes = [
        'xs' => 'px-2.5 py-1 text-xs rounded-sm gap-1',
        'sm' => 'px-3.5 py-1.5 text-sm rounded-md gap-1.5',
        'md' => 'px-5 py-2.5 text-sm rounded-md gap-2',
        'lg' => 'px-7 py-3.5 text-base rounded-md gap-2.5',
    ];

    $classes = \Illuminate\Support\Arr::toCssClasses([
        $baseClasses,
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
        'w-full' => $full,
        'opacity-50 cursor-not-allowed pointer-events-none' => $disabled,
        'opacity-75 pointer-events-none' => $loading,
    ]);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled || $loading) tabindex="-1" aria-disabled="true" @endif>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled || $loading) disabled @endif>
@endif
    @if($loading)
        <svg class="animate-spin -ml-1 mr-2 {{ $size === 'xs' || $size === 'sm' ? 'h-3 w-3' : 'h-4 w-4' }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @elseif($icon)
        {!! $icon !!}
    @endif
    {{ $slot }}
@if($href)
    </a>
@else
    </button>
@endif
