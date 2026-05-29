<?php

namespace App\Http\Requests\Posts;

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\ContentService;
use App\Services\PostMetadataService;
use App\Support\Hashtags\HashtagParser;
use App\Support\Posts\PostMood;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Throwable;

class PostCreationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Post::class) ?? false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function validateForUser(User $user, array $data): array
    {
        $data = self::normalizeFileArrays($data);
        $request = self::create('/posts', 'POST', Arr::except($data, ['media', 'photos', 'video', 'media_files']), [], self::filesFrom($data));
        $request->setContainer(app());
        $request->setRedirector(app(Redirector::class));
        $request->setUserResolver(fn (): User => $user);
        $request->validateResolved();

        return [
            ...$request->validated(),
            'media_files' => $request->mediaFiles(),
            'media_attachments' => $request->temporaryMediaAttachments(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');
        $linkPreview = $this->input('link_preview');

        $this->merge([
            'body' => app(ContentService::class)->plainText($this->input('body')),
            'status' => $this->input('status') ?? PostStatus::Published->value,
            'published_at' => $this->normalizeNullableString($this->input('published_at')),
            'scheduled_publish_at' => $this->normalizeNullableString($this->input('scheduled_publish_at') ?? $this->input('published_at')),
            'location' => $this->normalizeNullableString($this->input('location')),
            'location_display_text' => $this->normalizeNullableString($this->input('location_display_text') ?? $this->input('location')),
            'metadata' => app(PostMetadataService::class)->normalize(is_array($metadata) ? $metadata : null),
            'link_preview' => is_array($linkPreview) ? $linkPreview : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', Rule::in([
                PostStatus::Draft->value,
                PostStatus::Published->value,
                PostStatus::Scheduled->value,
            ])],
            'published_at' => ['nullable', 'date'],
            'scheduled_publish_at' => ['nullable', 'date'],
            'group_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
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
            'mood' => ['nullable', 'string', Rule::in(PostMood::values())],
            'location' => ['nullable', 'string', 'max:100'],
            'location_display_text' => ['nullable', 'string', 'max:120'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'original_post_id' => ['nullable', 'integer', Rule::exists('posts', 'id')],
            'quote_post_id' => ['nullable', 'integer', Rule::exists('posts', 'id')],
            'is_system_generated' => ['sometimes', 'boolean'],
            'system_source' => ['nullable', 'string', 'max:100'],
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
            'metadata.birthday_age' => ['nullable', 'integer', 'min:0', 'max:200'],
            'metadata.milestone_id' => ['nullable', 'integer'],
            'metadata.milestone_type' => ['nullable', 'string', 'max:100'],
            'link_preview' => ['nullable', 'array'],
            'link_preview.url' => ['nullable', 'url', 'max:500'],
            'link_preview.title' => ['nullable', 'string', 'max:200'],
            'link_preview.description' => ['nullable', 'string', 'max:500'],
            'link_preview.image' => ['nullable', 'string', 'max:500'],
            'link_preview.domain' => ['nullable', 'string', 'max:120'],
            'confirmed_duplicate' => ['sometimes', 'boolean'],
            'skip_duplicate_check' => ['sometimes', 'boolean'],
            'media' => ['nullable', 'array', 'max:5'],
            'media.*' => [
                'file',
                File::types(['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'])->max('20mb'),
            ],
            'media_files' => ['nullable', 'array', 'max:5'],
            'media_files.*' => [
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
            'media_attachments' => ['nullable', 'array', 'max:5'],
            'media_attachments.*.temporary_path' => ['required_with:media_attachments', 'string', 'max:500'],
            'media_attachments.*.media_type' => ['required_with:media_attachments', 'string', Rule::in(['image', 'video'])],
            'media_attachments.*.alt_text' => ['nullable', 'string', 'max:160'],
            'temporary_media' => ['nullable', 'array', 'max:5'],
            'temporary_media.*' => ['string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateHashtagLimit($validator);

            $mediaFiles = collect($this->mediaFiles());
            $temporaryMedia = collect($this->temporaryMediaAttachments());
            $status = PostStatus::tryFrom((string) ($this->input('status') ?? PostStatus::Published->value)) ?? PostStatus::Published;
            $publishedAt = null;
            $publishedAtInput = $this->input('published_at');
            $scheduledPublishAtInput = $this->input('scheduled_publish_at') ?: $publishedAtInput;
            $scheduledPublishAt = null;

            if ($publishedAtInput) {
                try {
                    $publishedAt = Carbon::parse((string) $publishedAtInput);
                } catch (Throwable) {
                    $validator->errors()->add('published_at', 'Publish date is invalid.');
                }
            }

            if ($scheduledPublishAtInput) {
                try {
                    $scheduledPublishAt = Carbon::parse((string) $scheduledPublishAtInput);
                } catch (Throwable) {
                    $validator->errors()->add('scheduled_publish_at', 'Publish date is invalid.');
                }
            }

            if ($mediaFiles->isEmpty() && $temporaryMedia->isEmpty()) {
                $body = trim((string) $this->input('body'));

                if ($status !== PostStatus::Draft && $body === '' && ! $this->input('original_post_id')) {
                    $validator->errors()->add('body', 'Add text or media before publishing.');
                }
            }

            $videoFiles = $mediaFiles->filter(
                fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'video/')
            );
            $imageFiles = $mediaFiles->filter(
                fn ($file): bool => str_starts_with((string) $file->getMimeType(), 'image/')
            );
            $temporaryVideos = $temporaryMedia->filter(fn (array $media): bool => $media['media_type'] === 'video');
            $temporaryImages = $temporaryMedia->filter(fn (array $media): bool => $media['media_type'] === 'image');
            $errorKey = ($this->hasFile('photos') || $this->hasFile('video')) ? 'video' : 'media';

            if (($videoFiles->count() + $temporaryVideos->count()) > 1) {
                $validator->errors()->add($errorKey, 'Only one video can be uploaded.');
            }

            if (($videoFiles->isNotEmpty() || $temporaryVideos->isNotEmpty()) && ($imageFiles->isNotEmpty() || $temporaryImages->isNotEmpty())) {
                $validator->errors()->add($errorKey, 'Video cannot be uploaded together with photos.');
            }

            if ($status === PostStatus::Draft && $publishedAt) {
                $validator->errors()->add('published_at', 'Draft posts cannot have a publish date.');
            }

            if ($status === PostStatus::Scheduled && ! $scheduledPublishAtInput) {
                $validator->errors()->add('scheduled_publish_at', 'Select a publish date for scheduled posts.');
            }

            if ($status === PostStatus::Scheduled && $scheduledPublishAt && $scheduledPublishAt->isPast()) {
                $validator->errors()->add('scheduled_publish_at', 'Scheduled posts must be set in the future.');
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

    /**
     * @return array<int, UploadedFile>
     */
    public function mediaFiles(): array
    {
        $mediaFiles = collect($this->file('media', []))
            ->merge($this->file('media_files', []))
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

    /**
     * @return list<array{temporary_path: string, media_type: string, alt_text: ?string, order: int}>
     */
    public function temporaryMediaAttachments(): array
    {
        $attachments = collect($this->input('media_attachments', []))
            ->filter(fn (mixed $media): bool => is_array($media))
            ->map(fn (array $media, int $index): array => [
                'temporary_path' => (string) ($media['temporary_path'] ?? ''),
                'media_type' => (string) ($media['media_type'] ?? 'image'),
                'alt_text' => $this->normalizeNullableString($media['alt_text'] ?? null),
                'order' => $index,
            ]);

        $temporaryPaths = collect($this->input('temporary_media', []))
            ->filter(fn (mixed $path): bool => is_string($path) && trim($path) !== '')
            ->map(fn (string $path, int $index): array => [
                'temporary_path' => $path,
                'media_type' => 'image',
                'alt_text' => null,
                'order' => $attachments->count() + $index,
            ]);

        return $attachments
            ->merge($temporaryPaths)
            ->filter(fn (array $media): bool => $media['temporary_path'] !== '' && in_array($media['media_type'], ['image', 'video'], true))
            ->values()
            ->all();
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function filesFrom(array $data): array
    {
        return collect(['media', 'photos', 'video', 'media_files'])
            ->filter(fn (string $key): bool => array_key_exists($key, $data))
            ->mapWithKeys(fn (string $key): array => [$key => $data[$key]])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeFileArrays(array $data): array
    {
        foreach (['media', 'photos', 'media_files'] as $key) {
            if (($data[$key] ?? null) instanceof UploadedFile) {
                $data[$key] = [$data[$key]];
            }
        }

        return $data;
    }
}
