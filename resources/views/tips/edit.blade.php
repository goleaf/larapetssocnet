@php
 $tipSlug = $tip->slug ?? $tip->getKey();
@endphp

<x-app-layout>
 <x-slot name="header">
 <h2 class="font-semibold text-xl text-gray-800 leading-tight">
 Edit Tip
 </h2>
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-900">
 <form method="POST"action="{{ route('tips.update', $tipSlug) }}"class="space-y-6">
 @csrf
 @method('PATCH')

 @include('tips.partials.form', ['tip'=> $tip])

 <div class="flex items-center gap-3">
 <x-primary-button>Save changes</x-primary-button>
 <a href="{{ route('tips.show', $tipSlug) }}"class="text-sm text-gray-600 hover:text-gray-900">Back</a>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
