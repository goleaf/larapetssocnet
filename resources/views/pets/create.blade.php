<x-app-layout>
 <x-slot name="header">
 <h2 class="font-semibold text-xl text-gray-400 leading-tight">
 Create Pet Profile
 </h2>
 </x-slot>

 <div class="py-8">
 <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
 <div class="bg-white shadow-sm sm:rounded-lg">
 <div class="p-6 text-gray-400">
 <form method="POST" action="{{ route('pets.store') }}" enctype="multipart/form-data" class="space-y-6">
 @csrf

 @include('pets.partials.form')

 <div class="flex items-center gap-3">
 <x-primary-button>Create profile</x-primary-button>
 <a href="{{ route('pets.explore') }}" class="text-sm text-gray-400 hover:text-gray-400">Cancel</a>
 </div>
 </form>
 </div>
 </div>
 </div>
 </div>
</x-app-layout>
