<x-app-layout>
 <x-slot name="header">
 <h2 class="font-semibold text-xl text-gray-400 leading-tight">
 Share a Pet Care Tip
 </h2>
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-400">
 <form method="POST" action="{{ route('tips.store') }}" class="space-y-6">
 @csrf

 @include('tips.partials.form')

 <div class="flex items-center gap-3">
 <x-primary-button>Submit tip</x-primary-button>
 <a href="{{ route('tips.index') }}" class="text-sm text-gray-400 hover:text-gray-400">Cancel</a>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
