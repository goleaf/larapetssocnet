<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Create Marketplace Listing" description="Publish a new listing with photos, details, and pricing." icon="🛍️">
 <x-slot name="action">
 <x-ui.button :href="route('marketplace.index')" variant="ghost" size="sm">Back to Marketplace</x-ui.button>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <div class="py-8">
 <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
 <x-ui.card padding="lg">
 <form method="POST" action="{{ route('marketplace.store') }}" enctype="multipart/form-data" class="space-y-6">
 @csrf

 @include('marketplace.partials.form', ['listing'=> $listing])

 <div class="flex items-center justify-end gap-2">
 <x-ui.button :href="route('marketplace.index')" variant="ghost">Cancel</x-ui.button>
 <x-ui.button type="submit" variant="primary">Create Listing</x-ui.button>
 </div>
 </form>
 </x-ui.card>
 </div>
 </div>
</x-app-layout>
