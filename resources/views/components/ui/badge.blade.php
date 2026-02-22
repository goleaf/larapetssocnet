@props([
    'variant' => 'default',
    'size' => 'md',
    'dot' => false,
    'pill' => true,
])

@php
    $variantClasses = [
        'default' => 'bg-cream text-fur border border-whisker/40',
        'primary' => 'bg-paw-light text-paw-dark',
        'success' => 'bg-leaf-light text-leaf',
        'danger'  => 'bg-rose-light text-rose',
        'warning' => 'bg-amber-light text-amber',
        'info'    => 'bg-sky-light text-sky',
        'dark'    => 'bg-bark text-cream',
    ][$variant] ?? 'bg-cream text-fur border border-whisker/40';

    $dotColors = [
        'default' => 'bg-fur',
        'primary' => 'bg-paw',
        'success' => 'bg-leaf',
        'danger'  => 'bg-rose',
        'warning' => 'bg-amber',
        'info'    => 'bg-sky',
        'dark'    => 'bg-cream',
    ][$variant] ?? 'bg-fur';

    $sizeClasses = [
        'sm' => 'px-2 py-0.5 text-2xs',
        'md' => 'px-2.5 py-1 text-xs',
    ][$size] ?? 'px-2.5 py-1 text-xs';

    $roundedClass = $pill ? 'rounded-pill' : 'rounded-sm';
@endphp

<span
    {{ $attributes->class([
        'inline-flex items-center gap-1.5 font-semibold leading-none',
        $variantClasses,
        $sizeClasses,
        $roundedClass,
    ]) }}
>
    @if ($dot)
        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $dotColors }}" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
