<x-app-layout>
 <x-slot name="header">
 <x-ui.page-header title="Share a Pet Care Tip" description="Write a practical tip for other pet owners." icon="🧠" />
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-900">
 <form method="POST" action="{{ route('tips.store') }}" class="space-y-6">
 @csrf

 @include('tips.partials.form')

 <div class="flex items-center gap-3">
 <x-ui.button variant="primary">Submit tip</x-ui.button>
 <a href="{{ route('tips.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
