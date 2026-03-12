@props([
'pet',
'size'=>'md',
])

<a href="{{ route('pets.show', ['pet'=> $pet->slug ?? $pet->getKey()]) }}" {{ $attributes->merge(['class'=>"shell-card flex-shrink-0 ".($size === 'sm' ? 'w-[120px]' : 'w-[160px]')." p-3 text-center transition-all hover:-translate-y-0.5 hover:shadow-card-hover snap-start"]) }}>
 <x-ui.avatar
 :src="$pet->getFirstMediaUrl('avatar')"
 :name="$pet->name"
 :size="$size === 'sm' ? 'sm' : 'md'"
 class="mx-auto {{ $size === 'sm' ? 'h-14 w-14' : 'h-20 w-20' }} border-2 border-cream"
 />
 <p class="mt-2 truncate text-sm font-semibold text-bark">{{ $pet->name }}</p>
 @if ($pet->species)
 <span class="mt-1 inline-block rounded-[var(--radius-soft)] px-2 py-0.5 text-2xs font-medium {{ [
    'dog' => 'bg-paw-light text-paw-dark',
    'cat' => 'bg-purple-100 text-purple-700',
    'bird' => 'bg-sky-light text-sky',
    'fish' => 'bg-blue-100 text-blue-600',
    'rabbit' => 'bg-rose-light text-rose',
    'hamster' => 'bg-amber-light text-amber',
    'reptile' => 'bg-leaf-light text-leaf',
 ][strtolower((string) $pet->species)] ?? 'bg-cream text-fur' }}">
 {{ ucfirst((string) $pet->species) }}
 </span>
 @endif
 @if ($pet->breed)
 <p class="mt-0.5 truncate text-2xs text-fur">{{ $pet->breed }}</p>
 @endif
</a>
