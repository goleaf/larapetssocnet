@props([
    'padding' => 'md',
    'hover' => false,
])

@php
    $paddingClasses = [
        'none' => '',
        'sm'   => 'p-3',
        'md'   => 'p-4 sm:p-5',
        'lg'   => 'p-5 sm:p-7',
    ][$padding] ?? 'p-4 sm:p-5';
@endphp

<section
    {{ $attributes->class([
        'bg-warm-white rounded-lg shadow-card border border-whisker/20',
        $paddingClasses,
        'transition-all duration-150 hover:shadow-card-hover hover:-translate-y-0.5' => $hover,
    ]) }}
>
    @if (isset($header))
        <div class="border-b border-whisker/30 pb-4 mb-4">
            {{ $header }}
        </div>
    @endif

    {{ $slot }}

    @if (isset($footer))
        <div class="border-t border-whisker/30 pt-4 mt-4">
            {{ $footer }}
        </div>
    @endif
</section>
