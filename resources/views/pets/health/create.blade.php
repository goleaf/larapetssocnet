@php
 $petSlug = $pet->slug ?? $pet->getKey();
@endphp

<x-app-layout>
 <x-slot name="header">
 <h2 class="font-semibold text-xl text-gray-800 leading-tight">
 Add Health Entry for {{ $pet->name ??'Pet'}}
 </h2>
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-900">
 <form method="POST" action="{{ route('pets.health.store', $petSlug) }}" class="space-y-6">
 @csrf

 @include('pets.health._form')

 <div class="flex items-center gap-3">
 <x-primary-button>Save entry</x-primary-button>
 <a href="{{ route('pets.health.index', $petSlug) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
