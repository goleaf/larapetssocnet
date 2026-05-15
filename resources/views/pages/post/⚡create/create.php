<?php

use App\Models\Content\Post;
use App\Models\Pets\Pet;
use App\Services\PostService;
use Carbon\CarbonImmutable;
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
#[Layout('layouts.app')]
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

    public string $status = 'published';

    public ?string $published_at = null;

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
                'status' => $validated['status'] ?? 'published',
                'published_at' => $validated['published_at'] ?? null,
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
            'status' => ['required', 'string', 'in:draft,published,scheduled'],
            'published_at' => ['nullable', 'date'],
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
            'status' => $this->status,
            'published_at' => $this->normalizeNullableString($this->published_at),
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

            $status = (string) ($this->status ?? 'published');
            $publishedAt = null;

            if ($this->published_at) {
                try {
                    $publishedAt = CarbonImmutable::parse($this->published_at);
                } catch (Throwable) {
                    $validator->errors()->add('published_at', 'Publish date is invalid.');
                }
            }

            if ($status === 'draft' && $publishedAt) {
                $validator->errors()->add('published_at', 'Draft posts cannot have a publish date.');
            }

            if ($status === 'scheduled' && ! $publishedAt) {
                $validator->errors()->add('published_at', 'Select a publish date for scheduled posts.');
            }

            if ($status === 'scheduled' && $publishedAt && $publishedAt->isPast()) {
                $validator->errors()->add('published_at', 'Scheduled posts must be set in the future.');
            }

            if ($status === 'published' && $publishedAt && $publishedAt->isFuture()) {
                $validator->errors()->add('published_at', 'Published posts cannot be scheduled in the future.');
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
