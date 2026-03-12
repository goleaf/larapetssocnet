<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Create Marketplace Listing" description="Publish a new listing with photos, details, and pricing." icon="🛍️">
 <x-slot name="action">
 <a href="{{ route('marketplace.index') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Marketplace</a>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="py-8">
 <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
 <form method="POST" action="{{ route('marketplace.store') }}" enctype="multipart/form-data" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
 @csrf

 @include('marketplace.partials.form', ['listing'=> $listing])

 <div class="flex items-center justify-end gap-2">
 <a href="{{ route('marketplace.index') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</a>
 <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Create Listing</button>
 </div>
 </form>
 </div>
 </div>
</x-app-layout>
