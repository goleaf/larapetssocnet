<?php

namespace App\Http\Requests\Posts;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Pets\Pet;
use App\Services\PostMetadataService;
use App\Support\Hashtags\HashtagParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Throwable;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Post::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');

        $this->merge([
            'status' => $this->input('status') ?? PostStatus::Published->value,
            'published_at' => $this->normalizeNullableString($this->input('published_at')),
            'location' => $this->normalizeNullableString($this->input('location')),
            'metadata' => app(PostMetadataService::class)->normalize(is_array($metadata) ? $metadata : null),
        ]);
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', Rule::in([
                PostStatus::Draft->value,
                PostStatus::Published->value,
                PostStatus::Scheduled->value,
            ])],
            'published_at' => ['nullable', 'date'],
            'pet_id' => [
                'nullable',
                'integer',
                $this->petPostPermissionRule(),
            ],
            'tagged_pets' => ['nullable', 'array'],
            'tagged_pets.*' => [
                'integer',
                $this->petPostPermissionRule(),
            ],
            'visibility' => ['nullable', 'string', Rule::in(Post::visibilityValues())],
            'location' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'metadata.link' => ['nullable', 'array'],
            'metadata.link.url' => ['nullable', 'url', 'max:500'],
            'metadata.link.title' => ['nullable', 'string', 'max:200'],
            'metadata.link.description' => ['nullable', 'string', 'max:500'],
            'metadata.link.image' => ['nullable', 'string', 'max:500'],
            'metadata.mood' => ['nullable', 'string', 'max:200'],
            'metadata.activity' => ['nullable', 'string', 'max:200'],
            'metadata.source' => ['nullable', 'string', 'max:200'],
            'metadata.context' => ['nullable', 'string', 'max:200'],
            'media' => ['nullable', 'array', 'max:5'],
            'media.*' => [
                'file',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'])->max('20mb'),
            ],
            'photos' => ['nullable', 'array', 'max:5'],
            'photos.*' => [
                'file',
                File::image()->max('20mb'),
            ],
            'video' => [
                'nullable',
                'file',
                File::types(['mp4', 'mov'])->max('20mb'),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateHashtagLimit($validator);

            $mediaFiles = collect($this->mediaFiles());
            $status = PostStatus::tryFrom((string) ($this->input('status') ?? PostStatus::Published->value)) ?? PostStatus::Published;
            $publishedAt = null;
            $publishedAtInput = $this->input('published_at');

            if ($publishedAtInput) {
                try {
                    $publishedAt = Carbon::parse((string) $publishedAtInput);
                } catch (Throwable) {
                    $validator->errors()->add('published_at', 'Publish date is invalid.');
                }
            }

            if ($mediaFiles->isEmpty()) {
                $body = trim((string) $this->input('body'));

                if ($status !== PostStatus::Draft && $body === '') {
                    $validator->errors()->add('body', 'Add text or media before publishing.');
                }
            }

            $videoFiles = $mediaFiles->filter(
                fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'video/')
            );

            $imageFiles = $mediaFiles->filter(
                fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'image/')
            );

            $errorKey = ($this->hasFile('photos') || $this->hasFile('video')) ? 'video' : 'media';

            if ($videoFiles->hasMany()) {
                $validator->errors()->add($errorKey, 'Only one video can be uploaded.');
            }

            if ($videoFiles->isNotEmpty() && $imageFiles->isNotEmpty()) {
                $validator->errors()->add($errorKey, 'Video cannot be uploaded together with photos.');
            }

            if ($status === PostStatus::Draft && $publishedAt) {
                $validator->errors()->add('published_at', 'Draft posts cannot have a publish date.');
            }

            if ($status === PostStatus::Scheduled && ! $publishedAt) {
                $validator->errors()->add('published_at', 'Select a publish date for scheduled posts.');
            }

            if ($status === PostStatus::Scheduled && $publishedAt && $publishedAt->isPast()) {
                $validator->errors()->add('published_at', 'Scheduled posts must be set in the future.');
            }

            if ($status === PostStatus::Published && $publishedAt && $publishedAt->isFuture()) {
                $validator->errors()->add('published_at', 'Published posts cannot be scheduled in the future.');
            }

            $petId = $this->input('pet_id');
            $visibility = $this->input('visibility') ?? Post::VISIBILITY_PUBLIC;

            if ($petId) {
                $pet = Pet::query()
                    ->select(['id', 'is_public', 'user_id', 'species', 'breed'])
                    ->whereKey((int) $petId)
                    ->first();

                if ($pet && ! (bool) $pet->is_public && $visibility === Post::VISIBILITY_PUBLIC) {
                    $validator->errors()->add('visibility', 'Public posts cannot be linked to a private pet.');
                }
            }
        });
    }

    private function validateHashtagLimit($validator): void
    {
        $body = (string) ($this->input('body') ?? '');
        $hashtags = app(HashtagParser::class)->extractAll($body);
        $max = (int) config('hashtags.max_per_post', 20);

        if (count($hashtags) > $max) {
            $validator->errors()->add('body', "You can use up to {$max} hashtags per post.");
        }
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function petPostPermissionRule(): mixed
    {
        $userId = (int) $this->user()?->id;

        return Rule::exists('pets', 'id')->where(function ($query) use ($userId): void {
            $query
                ->where('user_id', $userId)
                ->orWhereIn('id', function ($subQuery) use ($userId): void {
                    $subQuery
                        ->select('pet_id')
                        ->from('pet_owners')
                        ->where('user_id', $userId)
                        ->where(function ($permissionQuery): void {
                            $permissionQuery
                                ->where('can_post', true)
                                ->orWhereIn('role', ['owner', 'admin', 'poster']);
                        })
                        ->whereNotNull('accepted_at');
                });
        });
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function mediaFiles(): array
    {
        $mediaFiles = collect($this->file('media', []))
            ->merge($this->file('photos', []));

        $videoFile = $this->file('video');

        if ($videoFile instanceof UploadedFile) {
            $mediaFiles->push($videoFile);
        }

        return $mediaFiles
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->values()
            ->all();
    }
}
