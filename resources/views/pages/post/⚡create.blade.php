<?php

use App\Models\Pet;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('layouts::app')]
#[Title('Create Post')]
class extends Component
{
 use AuthorizesRequests;
 use WithFileUploads;

 public ?string $body = null;

 public ?int $pet_id = null;

 public array $tagged_pets = [];

 public string $visibility = Post::VISIBILITY_PUBLIC;

 public ?string $location = null;

 /**
 * @var array<int, UploadedFile>
 */
 public array $media = [];

 #[Computed]
 public function pets(): Collection
 {
 $user = auth()->user();

 if (! $user) {
 return collect();
 }

 return Pet::query()
 ->select(['id', 'name'])
 ->where('user_id', $user->getKey())
 ->orderBy('name')
 ->get();
 }

 public function save(PostService $posts): void
 {
 $this->authorize('create', Post::class);
 $validated = $this->validateData();

 $post = $posts->create(
 author: auth()->user(),
 data: [
 'body' => $validated['body'] ?? null,
 'pet_id' => $validated['pet_id'] ?? null,
 'tagged_pets' => $validated['tagged_pets'] ?? [],
 'visibility' => $validated['visibility'] ?? Post::VISIBILITY_PUBLIC,
 'location' => $validated['location'] ?? null,
 ],
 mediaFiles: $validated['media'] ?? [],
 );

 session()->flash('success', 'Post created successfully.');

 $this->redirectRoute('posts.show', ['post' => $post], navigate: true);
 }

 protected function rules(): array
 {
 $userId = (int) auth()->id();
 $petOwnershipRule = Rule::exists('pets', 'id')->where(
 fn (Builder $query): Builder => $query->where('user_id', $userId)
 );

 return [
 'body' => ['nullable', 'string', 'max:2000'],
 'pet_id' => ['nullable', 'integer', $petOwnershipRule],
 'tagged_pets' => ['nullable', 'array'],
 'tagged_pets.*' => ['integer', $petOwnershipRule],
 'visibility' => ['required', 'string', 'in:public,followers,private'],
 'location' => ['nullable', 'string', 'max:100'],
 'media' => ['nullable', 'array', 'max:5'],
 'media.*' => [
 'file',
 File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'])->max('20mb'),
 ],
 ];
 }

 private function validateData(): array
 {
 $payload = [
 'body' => $this->normalizeNullableString($this->body),
 'pet_id' => $this->pet_id,
 'tagged_pets' => collect($this->tagged_pets)
 ->map(fn ($petId): int => (int) $petId)
 ->filter(fn (int $petId): bool => $petId > 0)
 ->unique()
 ->values()
 ->all(),
 'visibility' => $this->visibility,
 'location' => $this->normalizeNullableString($this->location),
 'media' => $this->media,
 ];

 $validator = Validator::make($payload, $this->rules());

 $validator->after(function ($validator): void {
 $videoFiles = collect($this->media)->filter(
 fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'video/')
 );

 $imageFiles = collect($this->media)->filter(
 fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'image/')
 );

 if ($videoFiles->count() > 1) {
 $validator->errors()->add('media', 'Only one video can be uploaded.');
 }

 if ($videoFiles->isNotEmpty() && $imageFiles->isNotEmpty()) {
 $validator->errors()->add('media', 'Video cannot be uploaded together with photos.');
 }
 });

 return $validator->validate();
 }

 private function normalizeNullableString(?string $value): ?string
 {
 $normalized = trim((string) $value);

 return $normalized === '' ? null : $normalized;
 }
};
?>

<div class="mx-auto w-full max-w-3xl py-8">
 <div class="mb-6">
 <h1 class="text-2xl font-bold text-gray-900">Create New Post</h1>
 <p class="mt-1 text-sm text-gray-600">Share a new update with text, photos, or one video.</p>
 </div>

 <form wire:submit="save" class="rounded-lg bg-white p-6 shadow">
 <div class="mb-4">
 <label for="body" class="block text-sm font-medium text-gray-700">What's on your mind?</label>
 <textarea
 id="body"
 wire:model.blur="body"
 rows="4"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
 ></textarea>
 @error('body')
 <span class="text-sm text-red-600">{{ $message }}</span>
 @enderror
 </div>

 <div class="mb-4 grid gap-4 md:grid-cols-2">
 <div>
 <label for="visibility" class="block text-sm font-medium text-gray-700">Visibility</label>
 <select
 id="visibility"
 wire:model="visibility"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
 >
 <option value="public">Public</option>
 <option value="followers">Followers</option>
 <option value="private">Private</option>
 </select>
 @error('visibility')
 <span class="text-sm text-red-600">{{ $message }}</span>
 @enderror
 </div>

 <div>
 <label for="pet_id" class="block text-sm font-medium text-gray-700">Associate with Pet (optional)</label>
 <select
 id="pet_id"
 wire:model="pet_id"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
 >
 <option value="">-- None --</option>
 @forelse ($this->pets as $pet)
 <option value="{{ $pet->id }}">{{ $pet->name }}</option>
 @empty
 <option value="" disabled>No pets yet</option>
 @endforelse
 </select>
 @error('pet_id')
 <span class="text-sm text-red-600">{{ $message }}</span>
 @enderror
 </div>
 </div>

 <div class="mb-4">
 <label for="tagged_pets" class="block text-sm font-medium text-gray-700">Tag Pets</label>
 <select
 id="tagged_pets"
 wire:model="tagged_pets"
 multiple
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
 >
 @foreach ($this->pets as $pet)
 <option value="{{ $pet->id }}">{{ $pet->name }}</option>
 @endforeach
 </select>
 <p class="mt-1 text-xs text-gray-500">Hold Ctrl/Cmd to select multiple pets.</p>
 @error('tagged_pets')
 <span class="text-sm text-red-600">{{ $message }}</span>
 @enderror
 @error('tagged_pets.*')
 <span class="text-sm text-red-600">{{ $message }}</span>
 @enderror
 </div>

 <div class="mb-4">
 <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
 <input
 id="location"
 type="text"
 wire:model.blur="location"
 class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
 />
 @error('location')
 <span class="text-sm text-red-600">{{ $message }}</span>
 @enderror
 </div>

 <div class="mb-5 rounded border bg-gray-50 p-4">
 <label for="media" class="block text-sm font-medium text-gray-700">Media Uploads</label>
 <input
 id="media"
 type="file"
 wire:model="media"
 multiple
 accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime"
 class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:rounded file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
 />
 <p class="mt-1 text-xs text-gray-500">Upload up to 5 photos, or a single video file.</p>

 @error('media')
 <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
 @enderror
 @error('media.*')
 <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
 @enderror

 <div wire:loading wire:target="media" class="mt-2 text-xs font-medium text-indigo-600">
 Uploading media...
 </div>

 @if ($media !== [])
 <ul class="mt-3 space-y-1 text-xs text-gray-700">
 @foreach ($media as $file)
 <li wire:key="upload-{{ $file->getFilename() }}">{{ $file->getClientOriginalName() }}</li>
 @endforeach
 </ul>
 @endif
 </div>

 <div class="flex justify-end">
 <button
 type="submit"
 wire:loading.attr="disabled"
 wire:target="save,media"
 class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 data-loading:pointer-events-none data-loading:opacity-70"
 >
 <span wire:loading.remove wire:target="save">Post</span>
 <span wire:loading wire:target="save">Posting...</span>
 </button>
 </div>
 </form>
</div>
