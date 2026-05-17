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
'variant'=>'compact',
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
    $isBrowseVariant = $variant === 'browse';
    $speciesToneClasses = [
        'dog' => 'bg-paw-light text-paw-dark',
        'cat' => 'bg-purple-100 text-purple-700',
        'bird' => 'bg-sky-light text-sky',
        'fish' => 'bg-blue-100 text-blue-600',
        'rabbit' => 'bg-rose-light text-rose',
        'hamster' => 'bg-amber-light text-amber',
        'reptile' => 'bg-leaf-light text-leaf',
    ][strtolower((string) $resolvedSpecies)] ?? 'bg-cream text-fur';
@endphp

@if ($isBrowseVariant)
    <a
        href="{{ $resolvedHref }}"
        data-ui="pet-card"
        aria-label="{{ __('View profile for :name', ['name' => $resolvedName]) }}"
        {{ $attributes->merge(['class' => 'shell-card group flex min-h-full flex-col overflow-hidden p-0 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw']) }}
    >
        <div class="relative aspect-[4/3] w-full bg-[color:var(--ui-surface-muted)]">
            @if (filled($resolvedImage))
                <img src="{{ $resolvedImage }}" alt="{{ $resolvedName }}" class="h-full w-full object-cover" loading="lazy">
            @else
                <div class="flex h-full items-center justify-center text-5xl">🐾</div>
            @endif

            @if (filled($resolvedSpecies))
                <span class="absolute left-3 top-3 inline-flex min-h-8 items-center rounded-[var(--radius-soft)] px-2.5 text-xs font-semibold shadow-card {{ $speciesToneClasses }}">
                    {{ \Illuminate\Support\Str::headline((string) $resolvedSpecies) }}
                </span>
            @endif
        </div>

        <div class="flex flex-1 flex-col gap-3 p-4">
            <div>
                <h3 class="truncate text-lg font-semibold font-display text-bark group-hover:text-paw">{{ $resolvedName }}</h3>
                @if (filled($resolvedOwner))
                    <p class="mt-0.5 truncate text-xs shell-text-muted">By {{ $resolvedOwner }}</p>
                @endif
            </div>

            <div class="flex flex-wrap gap-2 text-xs shell-text-muted">
                @if (filled($resolvedBreed))
                    <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">{{ $resolvedBreed }}</span>
                @endif

                @if (filled($resolvedAge))
                    <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">{{ $resolvedAge }}</span>
                @endif

                @if (filled($resolvedLocation))
                    <span class="inline-flex min-h-8 items-center rounded-[var(--radius-soft)] border border-whisker/40 px-2.5">📍 {{ $resolvedLocation }}</span>
                @endif
            </div>

            <span class="mt-auto inline-flex min-h-11 items-center justify-center rounded-[var(--radius-soft)] border border-whisker/40 px-4 text-sm font-semibold text-paw transition-colors group-hover:border-paw-light group-hover:bg-paw-light group-hover:text-paw-dark">
                {{ $resolvedCtaLabel }}
            </span>
        </div>
    </a>
@else
    <a href="{{ $resolvedHref }}" data-ui="pet-card" {{ $attributes->merge(['class'=>"shell-card flex-shrink-0 ".($size === 'sm' ? 'w-[120px]' : 'w-[160px]')." p-3 text-center transition-all hover:-translate-y-0.5 hover:shadow-card-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-paw snap-start"]) }}>
        <x-ui.avatar
            :src="$resolvedImage"
            :name="$resolvedName"
            :size="$size === 'sm' ? 'sm' : 'md'"
            class="mx-auto {{ $size === 'sm' ? 'h-14 w-14' : 'h-20 w-20' }} border-2 border-cream"
        />
        <p class="mt-2 truncate text-sm font-semibold text-bark">{{ $resolvedName }}</p>
        @if (filled($resolvedSpecies))
            <span class="mt-1 inline-block rounded-[var(--radius-soft)] px-2 py-0.5 text-2xs font-medium {{ $speciesToneClasses }}">
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
@endif
