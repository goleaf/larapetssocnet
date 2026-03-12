<x-app-layout>
 @php
 $speciesOptions = collect(\App\Models\Pet::SPECIES)
 ->map(static fn (string $species): array => [
'value'=> $species,
'label'=> \Illuminate\Support\Str::headline($species),
 ])
 ->prepend([
'value'=>'',
'label'=>'All species',
 ])
 ->values()
 ->all();

 $sexOptions = [
 ['value'=>'','label'=>'Any'],
 ['value'=>'male','label'=>'Male'],
 ['value'=>'female','label'=>'Female'],
 ['value'=>'unknown','label'=>'Unknown'],
 ];

 $sortOptions = [
 ['value'=>'newest','label'=>'Newest'],
 ['value'=>'oldest','label'=>'Oldest'],
 ['value'=>'name_asc','label'=>'Name A-Z'],
 ['value'=>'name_desc','label'=>'Name Z-A'],
 ];

 $personalityTagsValue = collect($filters['personality_tags'] ?? [])
 ->filter()
 ->implode(', ');

 $availableCount = $pets->total();
 $speciesCount = $pets->getCollection()->pluck('species')->filter()->unique()->count();
 @endphp

 <x-slot name="header">
 <x-ui.page-header title="Adopt a Pet" subtitle="Browse pets currently marked as adoptable.">
 <x-slot name="action">
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.button :href="route('pets.explore')" variant="outline" size="sm">Explore All Pets</x-ui.button>
 @auth
 <x-ui.button :href="route('pets.create')" variant="primary" size="sm">Create Pet Profile</x-ui.button>
 @endauth
 </div>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="space-y-5">
 <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
 <x-ui.stat label="Available Now" :value="number_format($availableCount)" icon="🐾"/>
 <x-ui.stat label="Species in Results" :value="number_format($speciesCount)" icon="🧬"/>
 <x-ui.stat label="Status" value="Adoption" icon="🏡"/>
 </div>

 <x-ui.card>
 <form method="GET" action="{{ route('pets.adopt') }}" class="grid gap-3 md:grid-cols-12">
 <x-ui.input
 class="md:col-span-4"
 name="q"
 label="Search"
 :value="$filters['q']"
 placeholder="Name or breed"
 />

 <x-ui.select
 class="md:col-span-3"
 name="species"
 label="Species"
 :options="$speciesOptions"
 :selected="$filters['species']"
 />

 <x-ui.input
 class="md:col-span-3"
 name="personality_tags"
 label="Personality tags"
 :value="$personalityTagsValue"
 placeholder="playful, calm"
 />

 <x-ui.select
 class="md:col-span-2"
 name="sex"
 label="Sex"
 :options="$sexOptions"
 :selected="$filters['sex']"
 />

 <x-ui.select
 class="md:col-span-3"
 name="sort"
 label="Sort"
 :options="$sortOptions"
 :selected="$filters['sort']"
 />

 <div class="md:col-span-8"></div>

 <div class="flex items-end md:col-span-2">
 <x-ui.button type="submit" variant="primary" size="sm" class="w-full">Apply Filters</x-ui.button>
 </div>

 <div class="flex items-end md:col-span-2">
 <x-ui.button :href="route('pets.adopt')" variant="ghost" size="sm" class="w-full">Reset</x-ui.button>
 </div>
 </form>
 </x-ui.card>

 @if ($pets->isEmpty())
 <x-ui.card>
 <x-ui.empty-state
 icon="🐕"
 title="No Adoptable Pets Found"
 description="Try a different search or filter selection."
 />
 </x-ui.card>
 @else
 <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
 @foreach ($pets as $pet)
 @php
 $petSlug = $pet->slug ?? $pet->getKey();
 $feeLabel = filled($pet->adoption_fee)
 ?'$'.number_format((int) $pet->adoption_fee).'adoption fee'
 :'No adoption fee';
 $imageUrl = $pet->avatar_url;
 @endphp

 <x-pet-card
 :name="$pet->name ??'Unnamed pet'"
 :species="\Illuminate\Support\Str::headline((string) ($pet->species ??'Unknown'))"
 :breed="$pet->breed ?:'Mixed'"
 :age="$pet->age_formatted ?: \Illuminate\Support\Str::headline((string) ($pet->sex ??'unknown'))"
 :location="$feeLabel"
 :image="$imageUrl"
 :owner="$pet->owner?->name"
 cta-label="View Profile"
 :cta-href="route('pets.show', $petSlug)"
 />
 @endforeach
 </div>

 <x-ui.card>
 <x-ui.pagination :paginator="$pets"/>
 </x-ui.card>
 @endif
 </div>
</x-app-layout>
