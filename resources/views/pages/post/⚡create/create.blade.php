<div class="mx-auto w-full max-w-3xl pb-24 pt-6">
 <x-ui.page-header
 title="Create Post"
 description="Share a new update with text, photos, or one video."
 icon="P"
 class="mb-6"
 />

 <x-ui.card>
 <form wire:submit="save" class="space-y-5">
 <x-ui.textarea
 id="body"
 name="body"
 label="What's on your mind?"
 rows="4"
 wire:model.blur="body"
 />

 <div class="grid gap-4 md:grid-cols-2">
 <x-ui.select
 id="visibility"
 name="visibility"
 label="Visibility"
 wire:model="visibility"
 >
 <option value="public">Public</option>
 <option value="followers">Followers</option>
 <option value="private">Private</option>
 </x-ui.select>

 <x-ui.select
 id="pet_id"
 name="pet_id"
 label="Associate with Pet (optional)"
 wire:model="pet_id"
 >
 <option value="">-- None --</option>
 @forelse ($this->pets as $pet)
 <option value="{{ $pet->id }}">{{ $pet->name }}</option>
 @empty
 <option value="" disabled>No pets yet</option>
 @endforelse
 </x-ui.select>
 </div>

 <div class="space-y-1">
 <x-ui.select
 id="tagged_pets"
 name="tagged_pets"
 label="Tag Pets"
 wire:model="tagged_pets"
 multiple
 >
 @forelse ($this->pets as $pet)
 <option value="{{ $pet->id }}">{{ $pet->name }}</option>
 @empty
 <option value="" disabled>No pets yet</option>
 @endforelse
 </x-ui.select>

 <p class="text-xs text-fur">Hold Ctrl/Cmd to select multiple pets.</p>

 @error('tagged_pets.*')
 <span class="text-xs text-rose">{{ $message }}</span>
 @enderror
 </div>

 <x-ui.input
 id="location"
 name="location"
 label="Location"
 wire:model.blur="location"
 />

 <x-ui.panel padding="md" class="bg-cream/50">
 <x-ui.label for="media">Media Uploads</x-ui.label>
 <input
 id="media"
 type="file"
 wire:model="media"
 multiple
 accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
 class="mt-2 block w-full text-sm text-fur file:mr-4 file:border-0 file:bg-paw-light file:px-4 file:py-2 file:text-sm file:font-semibold file:text-paw-dark hover:file:bg-orange-200"
 />
 <p class="mt-1 text-xs text-fur">Upload up to 5 photos, or a single video file.</p>

 @error('media')
 <span class="mt-1 block text-xs text-rose">{{ $message }}</span>
 @enderror
 @error('media.*')
 <span class="mt-1 block text-xs text-rose">{{ $message }}</span>
 @enderror

 <div wire:loading wire:target="media" class="mt-2 text-xs font-semibold text-paw">
 Uploading media...
 </div>

 @if ($media !== [])
 <ul class="mt-3 space-y-1 text-xs text-fur">
 @forelse ($media as $file)
 <li wire:key="upload-{{ $file->getFilename() }}">{{ $file->getClientOriginalName() }}</li>
 @empty
 @endforelse
 </ul>
 @endif
 </x-ui.panel>

 <div class="flex justify-end">
 <x-ui.button
 type="submit"
 variant="primary"
 wire:loading.attr="disabled"
 wire:target="save,media"
 >
 <span wire:loading.remove wire:target="save">Post</span>
 <span wire:loading wire:target="save">Posting...</span>
 </x-ui.button>
 </div>
 </form>
 </x-ui.card>
</div>
