<x-ui.card padding="lg">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Gallery</h3>
            <p class="mt-1 text-sm text-gray-600">Upload new photos, adjust captions, and reorder the gallery.</p>
        </div>

        <form method="POST" action="{{ route('pets.gallery.store', $pet) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div>
                <x-ui.file-upload
                    id="pet_gallery_photos"
                    name="photos[]"
                    label="Add gallery photos"
                    multiple
                    accept="image/jpeg,image/png,image/webp,image/gif"
                    help="Upload up to {{ $galleryUploadMax }} photos at a time. {{ $galleryRemaining }} slots remaining."
                />
            </div>

            <div>
                <x-ui.button variant="primary" type="submit">Upload photos</x-ui.button>
            </div>
        </form>

        <div>
            <h4 class="text-sm font-semibold text-gray-900">Current gallery</h4>
            <x-ui.hint :error="$errors->first('order')" />
            @if ($galleryItems->isEmpty())
                <p class="mt-2 text-sm text-gray-500">No gallery photos uploaded yet.</p>
            @else
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($galleryItems as $media)
                        <div class="shell-card p-3 space-y-3">
                            <div class="flex items-start gap-3">
                                <img src="{{ $media['thumb_url'] }}" alt="{{ $media['alt_text'] !== '' ? $media['alt_text'] : 'Pet gallery photo' }}" class="h-24 w-24 rounded-[var(--radius-soft)] object-cover border border-whisker/30">
                                <div class="flex-1 space-y-2">
                                    <div class="flex flex-wrap gap-2">
                                        @if (!empty($media['move_left']))
                                            <form method="POST" action="{{ route('pets.gallery.reorder', $pet) }}">
                                                @csrf
                                                @method('PATCH')
                                                @foreach ($media['move_left'] as $id)
                                                    <input type="hidden" name="order[]" value="{{ $id }}">
                                                @endforeach
                                                <x-ui.button variant="secondary" type="submit" class="text-xs">Move left</x-ui.button>
                                            </form>
                                        @endif
                                        @if (!empty($media['move_right']))
                                            <form method="POST" action="{{ route('pets.gallery.reorder', $pet) }}">
                                                @csrf
                                                @method('PATCH')
                                                @foreach ($media['move_right'] as $id)
                                                    <input type="hidden" name="order[]" value="{{ $id }}">
                                                @endforeach
                                                <x-ui.button variant="secondary" type="submit" class="text-xs">Move right</x-ui.button>
                                            </form>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('pets.gallery.update', ['pet' => $pet, 'media' => $media['id']]) }}" class="space-y-2">
                                        @csrf
                                        @method('PATCH')
                                        <div>
                                            <x-ui.input id="caption-{{ $media['id'] }}" name="caption" type="text" label="Caption" :value="old('caption', $media['caption'])"/>
                                        </div>
                                        <div>
                                            <x-ui.input id="alt-{{ $media['id'] }}" name="alt_text" type="text" label="Alt text" :value="old('alt_text', $media['alt_text'])"/>
                                        </div>
                                        <x-ui.button variant="secondary" type="submit" class="text-xs">Save details</x-ui.button>
                                    </form>

                                    <form method="POST" action="{{ route('pets.gallery.destroy', ['pet' => $pet, 'media' => $media['id']]) }}" onsubmit="return confirm('Remove this photo from the gallery?');">
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
</x-ui.card>
