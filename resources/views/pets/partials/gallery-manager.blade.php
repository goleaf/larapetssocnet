@php
    $galleryItems = $galleryItems ?? collect();
    $galleryMax = $galleryMax ?? (int) config('pets.gallery.max_photos', 30);
    $galleryUploadMax = (int) config('pets.gallery.max_upload', 5);
    $galleryCount = $galleryItems->count();
    $galleryRemaining = max($galleryMax - $galleryCount, 0);
    $galleryIds = $galleryItems->pluck('id')->values()->all();
    $galleryLastIndex = count($galleryIds) - 1;
@endphp

<div class="bg-white shadow-sm sm:rounded-lg">
    <div class="p-6 space-y-6">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Gallery</h3>
            <p class="mt-1 text-sm text-gray-600">Upload new photos, adjust captions, and reorder the gallery.</p>
        </div>

        <form method="POST" action="{{ route('pets.gallery.store', $pet) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <x-input-label for="pet_gallery_photos" value="Add gallery photos"/>
                <input
                    id="pet_gallery_photos"
                    name="photos[]"
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    class="mt-1 block w-full rounded-md border border-gray-300 p-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700 hover:file:bg-indigo-100"
                />
                <p class="mt-1 text-xs text-gray-500">Upload up to {{ $galleryUploadMax }} photos at a time. {{ $galleryRemaining }} slots remaining.</p>
                <x-input-error :messages="$errors->get('photos')" class="mt-2"/>
                <x-input-error :messages="$errors->get('photos.*')" class="mt-2"/>
            </div>

            <div>
                <x-ui.button variant="primary" type="submit">Upload photos</x-ui.button>
            </div>
        </form>

        <div>
            <h4 class="text-sm font-semibold text-gray-900">Current gallery</h4>
            <x-input-error :messages="$errors->get('order')" class="mt-2"/>
            @if ($galleryItems->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No gallery photos uploaded yet.</p>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($galleryItems as $index => $media)
                        @php
                            $thumbUrl = $media->getUrl(\App\Models\Pet::MEDIA_CONVERSION_GALLERY_THUMB);
                            $thumbUrl = $thumbUrl !== '' ? $thumbUrl : $media->getUrl();
                            $caption = (string) ($media->getCustomProperty('caption') ?? '');
                            $altText = (string) ($media->getCustomProperty('alt_text') ?? '');

                            $moveLeft = $galleryIds;
                            if ($index > 0) {
                                [$moveLeft[$index - 1], $moveLeft[$index]] = [$moveLeft[$index], $moveLeft[$index - 1]];
                            }

                            $moveRight = $galleryIds;
                            if ($index < $galleryLastIndex) {
                                [$moveRight[$index + 1], $moveRight[$index]] = [$moveRight[$index], $moveRight[$index + 1]];
                            }
                        @endphp
                        <div class="rounded-lg border border-gray-200 p-3 space-y-3">
                            <div class="flex items-start gap-3">
                                <img src="{{ $thumbUrl }}" alt="{{ $altText !== '' ? $altText : 'Pet gallery photo' }}" class="h-24 w-24 rounded-md object-cover border border-gray-200">
                                <div class="flex-1 space-y-2">
                                    <div class="flex flex-wrap gap-2">
                                        @if ($index > 0)
                                            <form method="POST" action="{{ route('pets.gallery.reorder', $pet) }}">
                                                @csrf
                                                @method('PATCH')
                                                @foreach ($moveLeft as $id)
                                                    <input type="hidden" name="order[]" value="{{ $id }}">
                                                @endforeach
                                                <x-ui.button variant="secondary" type="submit" class="text-xs">Move left</x-ui.button>
                                            </form>
                                        @endif
                                        @if ($index < $galleryLastIndex)
                                            <form method="POST" action="{{ route('pets.gallery.reorder', $pet) }}">
                                                @csrf
                                                @method('PATCH')
                                                @foreach ($moveRight as $id)
                                                    <input type="hidden" name="order[]" value="{{ $id }}">
                                                @endforeach
                                                <x-ui.button variant="secondary" type="submit" class="text-xs">Move right</x-ui.button>
                                            </form>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('pets.gallery.update', ['pet' => $pet, 'media' => $media]) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <x-input-label for="caption-{{ $media->id }}" value="Caption"/>
                                            <x-text-input id="caption-{{ $media->id }}" name="caption" type="text" class="mt-1 block w-full" :value="old('caption', $caption)" />
                                        </div>
                                        <div>
                                            <x-input-label for="alt-{{ $media->id }}" value="Alt text"/>
                                            <x-text-input id="alt-{{ $media->id }}" name="alt_text" type="text" class="mt-1 block w-full" :value="old('alt_text', $altText)" />
                                        </div>
                                        <x-ui.button variant="secondary" type="submit" class="text-xs">Save details</x-ui.button>
                                    </form>

                                    <form method="POST" action="{{ route('pets.gallery.destroy', ['pet' => $pet, 'media' => $media]) }}" onsubmit="return confirm('Remove this photo from the gallery?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.button variant="danger" type="submit" class="text-xs">Remove</x-ui.button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
