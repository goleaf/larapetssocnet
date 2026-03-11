<x-app-layout>
 <x-slot name="header">
 <h2 class="font-semibold text-xl text-gray-800 leading-tight">
 Edit {{ $pet->name ??'Pet'}}
 </h2>
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-900">
 <form method="POST"action="{{ route('pets.update', $pet->slug ?? $pet->getKey()) }}"enctype="multipart/form-data"class="space-y-6">
 @csrf
 @method('PATCH')

 @include('pets.partials.form', ['pet'=> $pet])

 <div class="flex items-center gap-3">
 <x-primary-button>Save changes</x-primary-button>
 <a href="{{ route('pets.show', $pet->slug ?? $pet->getKey()) }}"class="text-sm text-gray-600 hover:text-gray-900">Back to profile</a>
 </div>
 </form>
 </div>
 </div>

 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6">
 <h3 class="text-sm font-semibold text-red-600">Danger zone</h3>
 <p class="mt-2 text-sm text-gray-600">Delete this pet profile (soft delete).</p>

 <form method="POST"action="{{ route('pets.destroy', $pet->slug ?? $pet->getKey()) }}"class="mt-4"onsubmit="return confirm('Delete this pet profile?');">
 @csrf
 @method('DELETE')
 <x-danger-button>Delete profile</x-danger-button>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
