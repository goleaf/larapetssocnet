@php
    $tip = $tip ?? null;
@endphp

<div class="space-y-6">
    <div>
        <x-input-label for="title" value="Title" />
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $tip?->title)" required />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <x-input-label for="species" value="Species" />
            <x-text-input id="species" name="species" type="text" class="mt-1 block w-full" :value="old('species', $tip?->species)" placeholder="dog, cat, bird..." />
            <x-input-error :messages="$errors->get('species')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="category" value="Category" />
            <x-text-input id="category" name="category" type="text" class="mt-1 block w-full" :value="old('category', $tip?->category)" placeholder="nutrition, training, hygiene..." />
            <x-input-error :messages="$errors->get('category')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="content" value="Tip content" />
        <textarea id="content" name="content" rows="8" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ old('content', $tip?->content) }}</textarea>
        <x-input-error :messages="$errors->get('content')" class="mt-2" />
    </div>
</div>
