<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Pets Available for Adoption" description="Browse adoptable pets and filter by location, species, and size." icon="🏡" />
 </x-slot>

 <div class="py-8">
 <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
 {{-- Filters --}}
 <x-ui.card padding="lg">
 <form method="GET" action="{{ route('adoption.index') }}"
 class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
 <div>
 <x-ui.select
 id="species"
 name="species"
 label="Species"
 :options="collect(['' => 'All species'])->merge(collect($species)->mapWithKeys(fn ($s) => [$s => (\App\Models\Pet::SPECIES_EMOJI[$s] ?? '🐾').' '.ucfirst($s)]))->all()"
 :selected="$filters['species'] ?? ''"
 />
 </div>

 <div>
 <x-ui.select
 id="size"
 name="size"
 label="Size"
 :options="collect(['' => 'Any size'])->merge(collect($sizes)->mapWithKeys(fn ($sz) => [$sz => ucfirst($sz)]))->all()"
 :selected="$filters['size'] ?? ''"
 />
 </div>

 <div>
 <x-ui.input id="location" name="location" label="Location" :value="$filters['location'] ?? ''" placeholder="City or region"/>
 </div>

 <div class="flex items-end">
 <x-ui.checkbox name="free" label="Free only" :checked="$filters['free'] ?? false"/>
 </div>

 <div class="flex items-end gap-2">
 <x-ui.button variant="primary">Filter</x-ui.button>
 <x-ui.button :href="route('adoption.index')" variant="ghost">Reset</x-ui.button>
 </div>
 </form>
 </x-ui.card>

 {{-- Results --}}
 @if($listings->isEmpty())
 <x-ui.card padding="lg" class="border-dashed">
 <div class="text-center text-sm text-gray-500">
 No adoptable pets match your filters.
 </div>
 </x-ui.card>
 @else
 <p class="text-sm text-gray-500">{{ $listings->total() }} pets available for adoption</p>

 <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
 @foreach($listings as $pet)
 <x-ui.card padding="none" class="border-emerald-200 overflow-hidden hover:shadow-card-hover transition-shadow">
 @if($pet->getFirstMediaUrl('avatar'))
 <img src="{{ $pet->getFirstMediaUrl('avatar') }}" alt="{{ $pet->name }}"
 class="w-full h-48 object-cover">
 @else
 <div class="w-full h-48 bg-emerald-50 flex items-center justify-center text-5xl">
 {{ $pet->species_emoji }}
 </div>
 @endif

 <div class="p-5">
 <div class="flex items-center gap-2 mb-2">
 <x-ui.badge variant="success" size="sm">For adoption</x-ui.badge>
 @if(!$pet->adoption_fee)
 <x-ui.badge variant="info" size="sm">Free</x-ui.badge>
 @endif
 </div>

 <h3 class="text-lg font-semibold text-gray-900">
 {{ $pet->species_emoji }} {{ $pet->name }}
 </h3>

 <p class="mt-1 text-sm text-gray-600">
 {{ ucfirst($pet->species) }}
 @if($pet->breed) &bull; {{ $pet->breed }} @endif
 @if($pet->age_formatted) &bull; {{ $pet->age_formatted }} @endif
 </p>

 @if($pet->owner?->location)
 <p class="mt-1 text-xs text-gray-500">📍 {{ $pet->owner->location }}</p>
 @endif

 @if($pet->personality_tags && is_array($pet->personality_tags))
 <div class="mt-2 flex flex-wrap gap-1">
 @foreach(array_slice($pet->personality_tags, 0, 3) as $tag)
 <x-ui.badge variant="secondary" size="sm">{{ \Illuminate\Support\Str::headline((string) $tag) }}</x-ui.badge>
 @endforeach
 </div>
 @endif

 @if($pet->adoption_fee)
 <p class="mt-2 text-sm font-semibold text-emerald-600">${{ number_format($pet->adoption_fee) }}
 </p>
 @endif

 <x-ui.button :href="route('pets.show', $pet->slug)" variant="ghost" size="sm" class="mt-4">
 View profile →
 </x-ui.button>
 </div>
 </x-ui.card>
 @endforeach
 </div>

 <div class="mt-4">
 {{ $listings->links() }}
 </div>
 @endif
 </div>
 </div>
</x-app-layout>
