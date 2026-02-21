@props([
    'post' => null,
    'pets' => [],
    'action',
    'method' => 'POST',
])

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-5 shell-card p-5" x-data="postFormState()">
    @csrf
    @if (strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="body" class="mb-1 block text-sm font-semibold">Post text</label>
        <textarea id="body" name="body" rows="5" maxlength="2000" x-model="body" @input="remaining = 2000 - body.length" class="w-full rounded-xl border border-[var(--ui-border)] p-3 text-sm" placeholder="What's on your mind? Use #hashtags and @mentions">{{ old('body', $post?->body) }}</textarea>
        <p class="mt-1 text-xs" :class="remaining < 50 ? 'text-rose-600' : 'shell-text-muted'" x-text="`${remaining} / 2000`"></p>
        <x-input-error :messages="$errors->get('body')" class="mt-1" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="mb-1 block text-sm font-semibold" for="pet_id">Tag a pet</label>
            <select id="pet_id" name="pet_id" class="w-full rounded-xl border border-[var(--ui-border)] p-2.5 text-sm">
                <option value="">Tag a pet</option>
                @foreach ($pets as $pet)
                    <option value="{{ $pet->id }}" @selected((string) old('pet_id', $post?->pet_id) === (string) $pet->id)>{{ $pet->name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('pet_id')" class="mt-1" />
        </div>

        <div>
            <label class="mb-1 block text-sm font-semibold" for="location">Location</label>
            <input id="location" name="location" type="text" maxlength="100" value="{{ old('location', $post?->location) }}" class="w-full rounded-xl border border-[var(--ui-border)] p-2.5 text-sm" placeholder="City or place">
        </div>
    </div>

    <div>
        <label class="mb-1 block text-sm font-semibold" for="tagged_pets">Tagged pets</label>
        @php
            $selectedTaggedPets = old('tagged_pets', $post?->tagged_pets ?? []);
            $selectedTaggedPets = is_array($selectedTaggedPets) ? array_map('intval', $selectedTaggedPets) : [];
        @endphp
        <select id="tagged_pets" name="tagged_pets[]" multiple class="w-full rounded-xl border border-[var(--ui-border)] p-2.5 text-sm">
            @foreach ($pets as $pet)
                <option value="{{ $pet->id }}" @selected(in_array((int) $pet->id, $selectedTaggedPets, true))>{{ $pet->name }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs shell-text-muted">Hold Cmd/Ctrl to select multiple pets.</p>
        <x-input-error :messages="$errors->get('tagged_pets')" class="mt-1" />
        <x-input-error :messages="$errors->get('tagged_pets.*')" class="mt-1" />
    </div>

    <div>
        <p class="mb-2 text-sm font-semibold">Visibility</p>
        <div class="grid gap-2 sm:grid-cols-3">
            @foreach ([['public','🌍 Public'],['followers','👥 Followers'],['private','🔒 Only me']] as [$value,$label])
                <label class="cursor-pointer rounded-xl border border-[var(--ui-border)] px-3 py-2 text-sm">
                    <input type="radio" class="sr-only" name="visibility" value="{{ $value }}" @checked(old('visibility', $post?->visibility ?? 'public') === $value)>
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    @if (! $post?->exists)
        <div class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-semibold" for="photos">Photos (up to 5)</label>
                <input id="photos" type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="w-full rounded-xl border border-[var(--ui-border)] p-2 text-sm" @change="onPhotos($event)">
                <p x-show="photoError" class="mt-1 text-xs text-rose-600" x-text="photoError"></p>
                <x-input-error :messages="$errors->get('photos')" class="mt-1" />
                <x-input-error :messages="$errors->get('photos.*')" class="mt-1" />
            </div>

            <div x-show="photoCount === 0">
                <label class="mb-1 block text-sm font-semibold" for="video">Video (max 50MB)</label>
                <input id="video" type="file" name="video" accept="video/mp4,video/quicktime,video/webm" class="w-full rounded-xl border border-[var(--ui-border)] p-2 text-sm" @change="onVideo($event)">
                <x-input-error :messages="$errors->get('video')" class="mt-1" />
            </div>

            <p class="text-xs shell-text-muted" x-text="typeBadge"></p>
        </div>
    @endif

    <div class="flex justify-end gap-2">
        <a href="{{ route('feed.index') }}" class="btn-base btn-ghost">Cancel</a>
        <button class="btn-base btn-primary" type="submit">{{ $post?->exists ? 'Save changes' : 'Post' }}</button>
    </div>
</form>

@once
<script>
function postFormState() {
    return {
        body: @js((string) old('body', $post?->body ?? '')),
        remaining: 2000 - @js(strlen((string) old('body', $post?->body ?? ''))),
        photoCount: 0,
        photoError: '',
        videoSelected: false,
        get typeBadge() {
            if (this.videoSelected) return '🎬 Video post';
            if (this.photoCount > 0) return `📷 Photo post (${this.photoCount} photos)`;
            return '📝 Text post';
        },
        onPhotos(event) {
            const maxPhotos = 5;
            const count = event.target.files.length;
            if (count > maxPhotos) {
                this.photoError = `You can upload up to ${maxPhotos} photos.`;
                event.target.value = '';
                this.photoCount = 0;
                return;
            }

            this.photoError = '';
            this.photoCount = count;
            if (this.photoCount > 0) {
                this.videoSelected = false;
            }
        },
        onVideo(event) {
            this.videoSelected = event.target.files.length > 0;
            if (this.videoSelected) {
                this.photoCount = 0;
                this.photoError = '';
                const photosInput = document.getElementById('photos');
                if (photosInput) photosInput.value = '';
            }
        }
    }
}
</script>
@endonce
