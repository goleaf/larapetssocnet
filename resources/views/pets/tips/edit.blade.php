<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Edit Tip" description="Refine your tip content and publishing details." icon="🛠️" />
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-900">
	 <form method="POST" action="{{ route('tips.update', $tip->slug ?? $tip->getKey()) }}" class="space-y-6">
 @csrf
 @method('PATCH')

 @include('pets.tips.partials.form', ['tip'=> $tip])

 <div class="flex items-center gap-3">
 <x-ui.button variant="primary">Save changes</x-ui.button>
	 <a href="{{ route('tips.show', $tip->slug ?? $tip->getKey()) }}" class="text-sm text-gray-600 hover:text-gray-900">Back</a>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
