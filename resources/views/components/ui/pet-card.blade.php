@props([
'pet',
'size'=>'md',
])

@php
 $avatarSize = $size ==='sm'?'sm':'md';
 $cardWidth = $size ==='sm'?'w-[120px]':'w-[160px]';
 $avatarDimension = $size ==='sm'?'h-14 w-14':'h-20 w-20';

 $speciesColors = [
'dog'=>'bg-paw-light text-paw-dark',
'cat'=>'bg-purple-100 text-purple-700',
'bird'=>'bg-sky-light text-sky',
'fish'=>'bg-blue-100 text-blue-600',
'rabbit'=>'bg-rose-light text-rose',
'hamster'=>'bg-amber-light text-amber',
'reptile'=>'bg-leaf-light text-leaf',
 ];

 $speciesClass = $speciesColors[strtolower((string) $pet->species)] ??'bg-cream text-fur';
 $petRoute = route('pets.show', ['slug'=> $pet->slug ?? $pet->getKey()]);
@endphp

<a href="{{ $petRoute }}" {{ $attributes->merge(['class'=>"flex-shrink-0 {$cardWidth} rounded-xl border border-whisker/30 bg-warm-white p-3 text-center transition-all hover:-translate-y-0.5 hover:shadow-card-hover snap-start"]) }}>
 <x-ui.avatar
 :src="$pet->getFirstMediaUrl('avatar')"
 :name="$pet->name"
 :size="$avatarSize"
 class="mx-auto {{ $avatarDimension }} border-2 border-cream"
 />
 <p class="mt-2 truncate text-sm font-semibold text-bark">{{ $pet->name }}</p>
 @if ($pet->species)
 <span class="mt-1 inline-block rounded-full px-2 py-0.5 text-2xs font-medium {{ $speciesClass }}">
 {{ ucfirst((string) $pet->species) }}
 </span>
 @endif
 @if ($pet->breed)
 <p class="mt-0.5 truncate text-2xs text-fur">{{ $pet->breed }}</p>
 @endif
</a>
