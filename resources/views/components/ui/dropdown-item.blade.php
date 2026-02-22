@props([
    'href' => null,
    'icon' => null,
    'variant' => 'default',
    'disabled' => false,
])

@php
    $baseClasses = 'flex items-center gap-2 px-4 py-2 text-sm w-full text-left transition-colors font-medium';
    
    $variantClasses = $variant === 'danger' 
        ? 'text-rose hover:bg-rose-light border-l-2 border-transparent hover:border-rose' 
        : 'text-bark hover:bg-cream border-l-2 border-transparent hover:border-paw';
        
    $disabledClasses = $disabled ? 'opacity-50 cursor-not-allowed pointer-events-none' : '';
    
    $classes = \Illuminate\Support\Arr::toCssClasses([
        $baseClasses,
        $variantClasses,
        $disabledClasses,
        $attributes->get('class'),
    ]);
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->except('class')->merge(['class' => $classes]) }} @if($disabled) aria-disabled="true" tabindex="-1" @endif>
        @if($icon)
            <div class="shrink-0 w-4 h-4 text-inherit flex items-center justify-center">
                {!! $icon !!}
            </div>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="button" {{ $attributes->except('class')->merge(['class' => $classes]) }} @if($disabled) disabled @endif>
        @if($icon)
            <div class="shrink-0 w-4 h-4 text-inherit flex items-center justify-center">
                {!! $icon !!}
            </div>
        @endif
        {{ $slot }}
    </button>
@endif
