<x-app-layout>
    <div class="max-w-3xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Edit Post</h1>
        </div>

        <x-post-form :post="$post" />
    </div>
</x-app-layout>