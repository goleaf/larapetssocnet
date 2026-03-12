<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="__('pets.title')" description="Browse and manage community pet profiles." icon="🐕" />
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($pets as $pet)
                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <a href="{{ route('pets.show', $pet) }}" class="text-base font-semibold text-gray-900 hover:text-emerald-700">
                            {{ $pet->name }}
                        </a>
                        <p class="mt-2 text-sm text-gray-500">
                            {{ $pet->species }} @if($pet->breed) • {{ $pet->breed }} @endif
                        </p>
                    </article>
                @empty
                    <p class="text-sm text-gray-500">{{ __('pets.no_results') }}</p>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $pets->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
