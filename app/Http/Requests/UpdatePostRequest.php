<?php

namespace App\Http\Requests;

use App\Enums\PostStatus;
use App\Models\Pet;
use App\Models\Post;
use App\Services\PostMetadataService;
use App\Support\Hashtags\HashtagParser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('post'));
    }

    protected function prepareForValidation(): void
    {
        $metadata = $this->input('metadata');

        $payload = [];

        if ($this->has('status')) {
            $payload['status'] = $this->input('status');
        }

        if ($this->has('published_at')) {
            $payload['published_at'] = $this->normalizeNullableString($this->input('published_at'));
        }

        if ($this->has('location')) {
            $payload['location'] = $this->normalizeNullableString($this->input('location'));
        }

        if ($this->has('metadata')) {
            $payload['metadata'] = app(PostMetadataService::class)->normalize(is_array($metadata) ? $metadata : null);
        }

        if ($payload !== []) {
            $this->merge($payload);
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in([
                PostStatus::Draft->value,
                PostStatus::Published->value,
                PostStatus::Scheduled->value,
                PostStatus::Archived->value,
            ])],
            'published_at' => ['nullable', 'date'],
            'visibility' => ['nullable', 'string', 'in:public,followers,private'],
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
            'pet_id' => [
                'nullable',
                'integer',
                Rule::exists('pets', 'id')->where(
                    fn ($query) => $query->where('user_id', (int) $this->user()?->id)
                ),
            ],
            'tagged_pets' => ['nullable', 'array'],
            'tagged_pets.*' => [
                'integer',
                Rule::exists('pets', 'id')->where(
                    fn ($query) => $query->where('user_id', (int) $this->user()?->id)
                ),
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->has('body')) {
                $body = (string) ($this->input('body') ?? '');
                $hashtags = app(HashtagParser::class)->extractAll($body);
                $max = (int) config('hashtags.max_per_post', 20);

                if (count($hashtags) > $max) {
                    $validator->errors()->add('body', "You can use up to {$max} hashtags per post.");
                }
            }

            $status = PostStatus::tryFrom((string) ($this->input('status') ?? PostStatus::Published->value)) ?? PostStatus::Published;
            $publishedAt = null;
            $publishedAtInput = $this->input('published_at');

            if ($publishedAtInput) {
                try {
                    $publishedAt = Carbon::parse((string) $publishedAtInput);
                } catch (\Throwable) {
                    $validator->errors()->add('published_at', 'Publish date is invalid.');
                }
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

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
