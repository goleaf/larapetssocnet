<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header :title="'Edit '.($pet->name ?? 'Pet')" description="Update profile details, gallery, and avatar settings." icon="🛠️" />
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
 @if (session('status'))
 <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">
 {{ session('status') }}
 </div>
 @endif
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-900">
 <form method="POST" action="{{ route('pets.update', $pet->slug ?? $pet->getKey()) }}" enctype="multipart/form-data" class="space-y-6">
 @csrf
 @method('PATCH')

 @include('pets.partials.form', ['pet'=> $pet])

 <div class="flex items-center gap-3">
 <x-ui.button variant="primary">Save changes</x-ui.button>
 <a href="{{ route('pets.show', $pet->slug ?? $pet->getKey()) }}" class="text-sm text-gray-600 hover:text-gray-900">Back to profile</a>
 </div>
 </form>
 </div>
 </div>

 @include('pets.partials.gallery-manager', ['pet' => $pet, 'galleryItems' => $galleryItems ?? collect(), 'galleryMax' => $galleryMax ?? (int) config('pets.gallery.max_photos', 30)])

 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 space-y-4">
 <h3 class="text-sm font-semibold text-gray-900">Avatar</h3>

 <div class="flex flex-wrap items-center gap-4">
 <img src="{{ $pet->avatar_url }}" alt="{{ $pet->name }}" class="h-16 w-16 rounded-full border border-gray-200 object-cover">

 <form method="POST" action="{{ route('pets.avatar.store', $pet) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
 @csrf
 <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif"
 class="block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700 hover:file:bg-indigo-100"/>
 <x-ui.button variant="primary">Update avatar</x-ui.button>
 <x-input-error :messages="$errors->get('avatar')" class="mt-1"/>
 </form>

 <form method="POST" action="{{ route('pets.avatar.destroy', $pet) }}" onsubmit="return confirm('Remove this avatar?');">
 @csrf
 @method('DELETE')
 <x-ui.button variant="danger">Remove avatar</x-ui.button>
 </form>
 </div>
 </div>
 </div>

 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6">
 <h3 class="text-sm font-semibold text-red-600">Danger zone</h3>
 <p class="mt-2 text-sm text-gray-600">Delete this pet profile (soft delete).</p>

 <form method="POST" action="{{ route('pets.destroy', $pet->slug ?? $pet->getKey()) }}" class="mt-4" onsubmit="return confirm('Delete this pet profile?');">
 @csrf
 @method('DELETE')
 <x-ui.button variant="danger">Delete profile</x-ui.button>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
