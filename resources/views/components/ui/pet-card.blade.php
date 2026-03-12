@props([
'pet'=> null,
'name'=> null,
'species'=> null,
'breed'=> null,
'age'=> null,
'location'=> null,
'image'=> null,
'owner'=> null,
'ctaLabel'=> null,
'ctaHref'=> null,
'size'=>'md',
])

@php
    $resolvedName = $name ?? $pet?->name ?? 'Unnamed Pet';
    $resolvedSpecies = $species ?? $pet?->species;
    $resolvedBreed = $breed ?? $pet?->breed;
    $resolvedAge = $age ?? $pet?->age_formatted;
    $resolvedLocation = $location ?? $pet?->location_text ?? $pet?->location;
    $resolvedImage = $image ?? ($pet ? $pet->getFirstMediaUrl('avatar') : null);
    $resolvedOwner = $owner ?? $pet?->user?->name;
    $resolvedHref = $ctaHref ?? ($pet ? route('pets.show', ['pet' => $pet->slug ?? $pet->getKey()]) : '#');
    $resolvedCtaLabel = $ctaLabel ?? __('pets.actions.view_profile');
@endphp
<a href="{{ $resolvedHref }}" {{ $attributes->merge(['class'=>"shell-card flex-shrink-0 ".($size === 'sm' ? 'w-[120px]' : 'w-[160px]')." p-3 text-center transition-all hover:-translate-y-0.5 hover:shadow-card-hover snap-start"]) }}>
    <x-ui.avatar
        :src="$resolvedImage"
        :name="$resolvedName"
        :size="$size === 'sm' ? 'sm' : 'md'"
        class="mx-auto {{ $size === 'sm' ? 'h-14 w-14' : 'h-20 w-20' }} border-2 border-cream"
    />
    <p class="mt-2 truncate text-sm font-semibold text-bark">{{ $resolvedName }}</p>
    @if (filled($resolvedSpecies))
        <span class="mt-1 inline-block rounded-[var(--radius-soft)] px-2 py-0.5 text-2xs font-medium {{ [
            'dog' => 'bg-paw-light text-paw-dark',
            'cat' => 'bg-purple-100 text-purple-700',
            'bird' => 'bg-sky-light text-sky',
            'fish' => 'bg-blue-100 text-blue-600',
            'rabbit' => 'bg-rose-light text-rose',
            'hamster' => 'bg-amber-light text-amber',
            'reptile' => 'bg-leaf-light text-leaf',
        ][strtolower((string) $resolvedSpecies)] ?? 'bg-cream text-fur' }}">
            {{ ucfirst((string) $resolvedSpecies) }}
        </span>
    @endif
    @if (filled($resolvedBreed))
        <p class="mt-0.5 truncate text-2xs text-fur">{{ $resolvedBreed }}</p>
    @endif
    @if (filled($resolvedAge))
        <p class="mt-0.5 truncate text-2xs text-fur">{{ $resolvedAge }}</p>
    @endif
    @if (filled($resolvedLocation))
        <p class="mt-0.5 truncate text-2xs text-fur">📍 {{ $resolvedLocation }}</p>
    @endif
    @if (filled($resolvedOwner))
        <p class="mt-0.5 truncate text-2xs text-fur">By {{ $resolvedOwner }}</p>
    @endif
    @if ($ctaLabel || $ctaHref)
        <span class="mt-2 inline-flex items-center justify-center text-2xs font-semibold text-paw">{{ $resolvedCtaLabel }}</span>
    @endif
</a>
