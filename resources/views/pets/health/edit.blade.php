<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Edit Health Entry" description="Update details for this health record." icon="📝" />
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-900">
	 <form method="POST" action="{{ route('pets.health.update', ['slug'=> $pet->slug ?? $pet->getKey(),'healthLog'=> $log->getKey()]) }}" class="space-y-6">
 @csrf
 @method('PATCH')

 @include('pets.health._form', ['log'=> $log])

 <div class="flex items-center gap-3">
 <x-ui.button variant="primary">Save changes</x-ui.button>
	 <a href="{{ route('pets.health.index', $pet->slug ?? $pet->getKey()) }}" class="text-sm text-gray-600 hover:text-gray-900">Back</a>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
