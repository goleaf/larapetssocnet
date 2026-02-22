@props([
    'src' => null,
    'name' => 'User',
    'size' => 'md',
    'online' => false,
])

@php
    $sizes = [
        'xs' => 'w-6 h-6 text-2xs',
        'sm' => 'w-8 h-8 text-xs',
        'md' => 'w-10 h-10 text-sm',
        'lg' => 'w-14 h-14 text-base',
        'xl' => 'w-20 h-20 text-xl',
    ];
    
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    
    $onlineSizes = [
        'xs' => 'w-1.5 h-1.5 border-[1.5px] right-0 bottom-0',
        'sm' => 'w-2.5 h-2.5 border-2 right-0 bottom-0',
        'md' => 'w-3 h-3 border-2 right-0 bottom-0',
        'lg' => 'w-4 h-4 border-2 right-0.5 bottom-0.5',
        'xl' => 'w-5 h-5 border-[3px] right-1 bottom-1',
    ];
    
    $onlineClass = $onlineSizes[$size] ?? $onlineSizes['md'];

    $initials = collect(explode(' ', $name))
        ->map(fn ($segment) => substr($segment, 0, 1))
        ->take(2)
        ->join('');
        
    $colors = [
        'bg-paw-light text-paw-dark',
        'bg-leaf-light text-leaf',
        'bg-sky-light text-sky',
        'bg-amber-light text-amber',
        'bg-rose-light text-rose',
    ];
    
    // Hash-based consistent color selection
    $colorIndex = abs(crc32($name)) % count($colors);
    $colorClass = $colors[$colorIndex];
@endphp

<div {{ $attributes->merge(['class' => "relative inline-block shrink-0"]) }}>
    @if($src)
        <img 
            src="{{ $src }}" 
            alt="{{ $name }}" 
            class="{{ $sizeClass }} rounded-pill object-cover bg-cream border border-whisker/30" 
        />
    @else
        <div class="{{ $sizeClass }} rounded-pill flex items-center justify-center font-bold font-display uppercase border border-whisker/30 {{ $colorClass }}">
            {{ $initials }}
        </div>
    @endif

    @if($online)
        <span class="absolute block rounded-pill bg-leaf border-warm-white {{ $onlineClass }}"></span>
    @endif
</div>
