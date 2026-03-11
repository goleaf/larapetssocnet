<x-app-layout>
 <x-slot name="header">
 <h2 class="font-semibold text-xl text-gray-800 leading-tight">
 🐾 Pets Available for Adoption
 </h2>
 </x-slot>

 <div class="py-8">
 <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
 {{-- Filters --}}
 <div class="bg-white shadow-sm sm:rounded-lg p-6">
 <form method="GET" action="{{ route('adoption.index') }}"
 class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
 <div>
 <x-input-label for="species" value="Species"/>
 <select id="species" name="species"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
 <option value="">All species</option>
 @foreach($species as $s)
 <option value="{{ $s }}"@selected(($filters['species'] ??'') === $s)>
 {{ \App\Models\Pet::SPECIES_EMOJI[$s] ??'🐾'}} {{ ucfirst($s) }}
 </option>
 @endforeach
 </select>
 </div>

 <div>
 <x-input-label for="size" value="Size"/>
 <select id="size" name="size"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
 <option value="">Any size</option>
 @foreach($sizes as $sz)
 <option value="{{ $sz }}"@selected(($filters['size'] ??'') === $sz)>{{ ucfirst($sz) }}
 </option>
 @endforeach
 </select>
 </div>

 <div>
 <x-input-label for="location" value="Location"/>
 <x-text-input id="location" name="location" class="mt-1 block w-full"
 :value="$filters['location'] ??''" placeholder="City or region"/>
 </div>

 <div class="flex items-end">
 <label class="inline-flex items-center gap-2 pb-2">
 <input type="checkbox" name="free" value="1"@checked($filters['free'] ?? false)
 class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
 <span class="text-sm text-gray-700">Free only</span>
 </label>
 </div>

 <div class="flex items-end gap-2">
 <x-primary-button>Filter</x-primary-button>
 <a href="{{ route('adoption.index') }}"
 class="text-sm text-gray-600 hover:text-gray-900">Reset</a>
 </div>
 </form>
 </div>

 {{-- Results --}}
 @if($listings->isEmpty())
 <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500">
 No adoptable pets match your filters.
 </div>
 @else
 <p class="text-sm text-gray-500">{{ $listings->total() }} pets available for adoption</p>

 <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
 @foreach($listings as $pet)
 <article
 class="rounded-2xl border border-emerald-200 bg-white shadow-sm overflow-hidden hover:shadow-md transition-shadow">
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
 <span
 class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">For
 adoption</span>
 @if(!$pet->adoption_fee)
 <span
 class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700">Free</span>
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
 <span
 class="inline-flex rounded-full bg-purple-50 px-2 py-0.5 text-xs text-purple-700">{{ $tag }}</span>
 @endforeach
 </div>
 @endif

 @if($pet->adoption_fee)
 <p class="mt-2 text-sm font-semibold text-emerald-600">${{ number_format($pet->adoption_fee) }}
 </p>
 @endif

 <a href="{{ route('pets.show', $pet->slug) }}"
 class="mt-4 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800">
 View profile →
 </a>
 </div>
 </article>
 @endforeach
 </div>

 <div class="mt-4">
 {{ $listings->links() }}
 </div>
 @endif
 </div>
 </div>
</x-app-layout>