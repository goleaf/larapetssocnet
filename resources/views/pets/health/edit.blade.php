@php
    $petSlug = $pet->slug ?? $pet->getKey();
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Edit Health Entry
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('pets.health.update', ['slug' => $petSlug, 'healthLog' => $log->getKey()]) }}" class="space-y-6">
                        @csrf
                        @method('PATCH')

                        @include('pets.health._form', ['log' => $log])

                        <div class="flex items-center gap-3">
                            <x-primary-button>Save changes</x-primary-button>
                            <a href="{{ route('pets.health.index', $petSlug) }}" class="text-sm text-gray-600 hover:text-gray-900">Back</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
