<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header :title="'Add Health Entry for '.($pet->name ?? 'Pet')" description="Log symptoms, treatments, and important notes." icon="🩺" />
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-900">
	 <form method="POST" action="{{ route('pets.health.store', $pet->slug ?? $pet->getKey()) }}" class="space-y-6">
 @csrf

 @include('pets.health._form')

 <div class="flex items-center gap-3">
 <x-ui.button variant="primary">Save entry</x-ui.button>
	 <a href="{{ route('pets.health.index', $pet->slug ?? $pet->getKey()) }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
