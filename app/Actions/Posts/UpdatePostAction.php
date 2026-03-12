<?php

namespace App\Actions\Posts;

use App\Actions\Pets\AttachPetToPostAction;
use App\Actions\Pets\DetachPetFromPostAction;
use App\Models\Pet;
use App\Models\Post;
use App\Models\User;
use App\Services\ContentService;
use Illuminate\Support\Facades\DB;

class UpdatePostAction
{
    public function __construct(
        private readonly ContentService $content,
        private readonly ProcessTagsAction $processTags,
        private readonly AttachPetToPostAction $attachPetToPostAction,
        private readonly DetachPetFromPostAction $detachPetFromPostAction,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Post $post, array $data): Post
    {
        return DB::transaction(function () use ($actor, $post, $data): Post {
            $nextBody = array_key_exists('body', $data)
                ? $this->normalizeNullableString($data['body'])
                : $post->body;

            $post->update([
                'body' => $nextBody,
                'body_html' => $nextBody ? $this->content->process($nextBody) : null,
                'visibility' => $data['visibility'] ?? $post->visibility,
                'location' => $this->normalizeNullableString($data['location'] ?? $post->location),
                'tagged_pets' => $data['tagged_pets'] ?? $post->tagged_pets,
            ]);

            $this->processTags->handle($post);

            $this->syncPetAttachment($actor, $post, $data);

            return $post->fresh() ?? $post;
        });
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncPetAttachment(User $actor, Post $post, array $data): void
    {
        if (! array_key_exists('pet_id', $data) && ! array_key_exists('tagged_pets', $data)) {
            return;
        }

        $nextPetId = $data['pet_id'] ?? ($data['tagged_pets'][0] ?? null);
        $nextPetId = $nextPetId ? (int) $nextPetId : null;

        if ($nextPetId === null) {
            $currentPetId = $post->pet_id ? (int) $post->pet_id : null;

            if (! $currentPetId) {
                return;
            }

            $currentPet = Pet::query()->whereKey($currentPetId)->first();

            if ($currentPet) {
                $this->detachPetFromPostAction->handle($actor, $post, $currentPet);
            }

            return;
        }

        $pet = Pet::query()->whereKey($nextPetId)->first();

        if (! $pet) {
            return;
        }

        $this->attachPetToPostAction->handle($actor, $post, $pet);
    }
}
