@props([
    'padding' => 'md',
    'hover' => false,
])

@php
    $paddings = [
        'none' => '',
        'sm' => 'p-3',
        'md' => 'p-5',
        'lg' => 'p-7',
    ];

    $paddingClass = $paddings[$padding] ?? $paddings['md'];

    $classes = \Illuminate\Support\Arr::toCssClasses([
        'bg-warm-white rounded-lg shadow-card',
        $hover ? 'transition-all duration-150 hover:shadow-card-hover hover:-translate-y-0.5 cursor-pointer' : '',
        $attributes->get('class'),
    ]);
@endphp

<div {{ $attributes->except('class')->merge(['class' => $classes]) }}>
    <div class="{{ $paddingClass }}">
        @if(isset($header))
            {{ $header }}
        @endif
        
        {{ $slot }}
        
        @if(isset($footer))
            <div class="border-t border-whisker/40 mt-4 pt-4">
                {{ $footer }}
            </div>
        @endif
    </div>
</div>
