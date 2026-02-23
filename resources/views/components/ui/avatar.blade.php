@props([
'src'=> null,
'name'=>'User',
'size'=>'md',
'online'=> false,
])

@php
 $sizes = [
'xs'=>'h-6 w-6 text-[0.625rem]',
'sm'=>'h-8 w-8 text-xs',
'md'=>'h-10 w-10 text-sm',
'lg'=>'h-14 w-14 text-base',
'xl'=>'h-20 w-20 text-xl',
'2xl'=>'h-24 w-24 text-2xl',
 ];

 $sizeClass = $sizes[(string) $size] ?? $sizes['md'];

 $onlineSizes = [
'xs'=>'h-1.5 w-1.5 border-[1.5px] bottom-0 right-0',
'sm'=>'h-2.5 w-2.5 border-2 bottom-0 right-0',
'md'=>'h-3 w-3 border-2 bottom-0 right-0',
'lg'=>'h-4 w-4 border-2 bottom-0.5 right-0.5',
'xl'=>'h-5 w-5 border-[3px] bottom-1 right-1',
'2xl'=>'h-6 w-6 border-[3px] bottom-1 right-1',
 ];

 $onlineClass = $onlineSizes[(string) $size] ?? $onlineSizes['md'];

 $displayName = trim((string) $name) !==''? (string) $name :'User';

 $initials = collect(preg_split('/\s+/', $displayName, -1, PREG_SPLIT_NO_EMPTY) ?: [])
 ->map(static fn (string $segment): string => mb_substr($segment, 0, 1))
 ->take(2)
 ->join('');

 $initials = $initials !==''? $initials :'U';

 $colors = [
'bg-paw-light text-paw-dark',
'bg-leaf-light text-leaf',
'bg-sky-light text-sky',
'bg-amber-light text-amber',
'bg-rose-light text-rose',
 ];

 $colorClass = $colors[abs(crc32($displayName)) % count($colors)];
 $imageSource = is_string($src) ? trim($src) : null;
@endphp

<div {{ $attributes->merge(['class'=>'relative inline-block shrink-0']) }}>
 @if(filled($imageSource))
 <img
 src="{{ $imageSource }}"
 alt="{{ $displayName }}"
 loading="lazy"
 class="{{ $sizeClass }} rounded-pill border border-whisker/30 bg-cream object-cover"
 >
 @else
 <div class="{{ $sizeClass }} {{ $colorClass }} flex items-center justify-center rounded-pill border border-whisker/30 font-bold font-display uppercase">
 {{ $initials }}
 </div>
 @endif

 @if($online)
 <span class="absolute block rounded-pill border-warm-white bg-leaf {{ $onlineClass }}"></span>
 @endif
</div>
