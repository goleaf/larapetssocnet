<x-app-layout>
 <x-slot name="header">
 <div class="flex items-center justify-between gap-3">
 <h2 class="text-xl font-semibold text-gray-800 leading-tight">Edit Listing</h2>
 <a href="{{ route('marketplace.show', $listing) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Listing</a>
 </div>
 </x-slot>

 <div class="py-8">
 <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
 <form method="POST" action="{{ route('marketplace.update', $listing) }}"enctype="multipart/form-data" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
 @csrf
 @method('PATCH')

 @include('marketplace.partials.form', ['listing'=> $listing])

 <div class="flex items-center justify-end gap-2">
 <a href="{{ route('marketplace.show', $listing) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
 <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Changes</button>
 </div>
 </form>
 </div>
 </div>
</x-app-layout>
