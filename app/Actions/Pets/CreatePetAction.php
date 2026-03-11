<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\PetTag;
use App\Models\User;
use App\Services\ContentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePetAction
{
    public function __construct(private ContentService $contentService) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $galleryPhotos
     */
    public function handle(User $owner, array $attributes, ?UploadedFile $avatar = null, array $galleryPhotos = []): Pet
    {
        return DB::transaction(function () use ($owner, $attributes, $avatar, $galleryPhotos): Pet {
            $bio = isset($attributes['bio']) ? (string) $attributes['bio'] : null;
            $birthdate = $attributes['birthdate'] ?? $attributes['date_of_birth'] ?? $attributes['birth_date'] ?? null;
            $tags = $this->normalizeTags($attributes['personality_tags'] ?? []);

            $pet = Pet::query()->create([
                'user_id' => $owner->getKey(),
                'name' => (string) $attributes['name'],
                'species' => (string) $attributes['species'],
                'breed' => $attributes['breed'] ?? null,
                'sex' => $attributes['sex'] ?? ($attributes['gender'] ?? 'unknown'),
                'gender' => $attributes['gender'] ?? ($attributes['sex'] ?? 'unknown'),
                'size' => $attributes['size'] ?? null,
                'birth_date' => $birthdate,
                'date_of_birth' => $birthdate,
                'age_text' => $attributes['age_text'] ?? null,
                'bio' => $bio,
                'bio_html' => $bio ? $this->contentService->process($bio) : null,
                'personality_tags' => $tags,
                'is_public' => (bool) ($attributes['is_public'] ?? true),
                'is_deceased' => (bool) ($attributes['is_deceased'] ?? false),
                'is_adoptable' => (bool) ($attributes['is_adoptable'] ?? false),
            ]);

            if ($avatar instanceof UploadedFile) {
                $pet->addMedia($avatar)->toMediaCollection('avatar');
            }

            $this->syncTags($pet, $tags);

            foreach ($galleryPhotos as $photo) {
                if ($photo instanceof UploadedFile) {
                    $pet->addMedia($photo)->toMediaCollection('gallery');
                }
            }

            $owner->increment('pets_count');

            return $pet->refresh();
        });
    }

    /**
     * @return list<string>
     */
    private function normalizeTags(mixed $rawTags): array
    {
        $tags = is_string($rawTags) ? explode(',', $rawTags) : Arr::wrap($rawTags);

        return collect($tags)
            ->map(static fn ($tag): string => trim((string) $tag))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncTags(Pet $pet, array $tags): void
    {
        if ($tags === []) {
            return;
        }

        foreach ($tags as $tag) {
            $slug = Str::slug($tag);

            if ($slug === '') {
                continue;
            }

            PetTag::query()->firstOrCreate([
                'pet_id' => $pet->getKey(),
                'slug' => $slug,
            ], [
                'name' => $tag,
            ]);
        }
    }
}
