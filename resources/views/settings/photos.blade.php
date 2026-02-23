<x-settings-layout>
    <div class="space-y-6">
        <div>
            <h3 class="text-lg font-medium leading-6 text-gray-900">Photo Galleries</h3>
            <p class="mt-1 text-sm text-gray-500">
                Create galleries, upload photos, and choose a cover image for each gallery.
            </p>
        </div>

        <div class="space-y-8">
            <section>
                <h4 class="text-sm font-semibold text-gray-900">Create new gallery</h4>
                <form action="{{ route('settings.photos') === url()->current() ? route('photo-galleries.store') : route('photo-galleries.store') }}"
                      method="POST"
                      class="mt-4 space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-1">
                            <x-input-label for="title" value="Title" />
                            <x-text-input id="title" name="title" type="text" class="mt-1 block w-full"
                                          :value="old('title')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('title')" />
                        </div>
                        <div class="sm:col-span-1">
                            <x-input-label for="description" value="Description (optional)" />
                            <textarea id="description" name="description" rows="2"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('description') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('description')" />
                        </div>
                    </div>
                    <div class="flex justify-end border-t border-gray-200 pt-4">
                        <x-primary-button>Create Gallery</x-primary-button>
                    </div>
                </form>
            </section>

            <section>
                <h4 class="text-sm font-semibold text-gray-900">Your galleries</h4>

                @if ($galleries->isEmpty())
                    <p class="mt-2 text-sm text-gray-500">
                        You haven't created any galleries yet. Create your first gallery above, then add photos from your profile Photos tab.
                    </p>
                @else
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($galleries as $gallery)
                            @php
                                $coverUrl = $gallery->coverUrl();
                            @endphp
                            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                                @if ($coverUrl !== '')
                                    <img src="{{ $coverUrl }}" alt="{{ $gallery->title }} cover"
                                         class="h-32 w-full object-cover">
                                @else
                                    <div class="flex h-32 w-full items-center justify-center bg-gray-50 text-3xl">
                                        📷
                                    </div>
                                @endif
                                <div class="px-4 py-3 space-y-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-900">
                                                {{ $gallery->title }}
                                            </p>
                                            @if ($gallery->description)
                                                <p class="mt-0.5 line-clamp-2 text-xs text-gray-500">
                                                    {{ $gallery->description }}
                                                </p>
                                            @endif
                                        </div>
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                            {{ $gallery->media_count }} {{ Str::plural('photo', $gallery->media_count) }}
                                        </span>
                                    </div>

                                    @if ($gallery->media->isNotEmpty())
                                        <p class="mt-2 text-xs text-gray-500">
                                            To change the cover image or add more photos, use the Photos tab on your public profile.
                                        </p>
                                    @else
                                        <p class="mt-2 text-xs text-gray-500">
                                            No photos in this gallery yet. Add some from your profile Photos tab.
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-settings-layout>

