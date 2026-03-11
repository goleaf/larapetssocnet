<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Models\PetTag;
use App\Services\ContentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdatePetAction
{
    public function __construct(private ContentService $contentService) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $galleryPhotos
     */
    public function handle(Pet $pet, array $attributes, ?UploadedFile $avatar = null, array $galleryPhotos = []): Pet
    {
        return DB::transaction(function () use ($pet, $attributes, $avatar, $galleryPhotos): Pet {
            $bio = array_key_exists('bio', $attributes) ? (string) ($attributes['bio'] ?? '') : $pet->bio;
            $birthdate = $attributes['birthdate'] ?? $attributes['date_of_birth'] ?? $attributes['birth_date'] ?? null;
            $tags = array_key_exists('personality_tags', $attributes)
                ? $this->normalizeTags($attributes['personality_tags'])
                : (array) $pet->personality_tags;

            $pet->fill([
                'name' => $attributes['name'] ?? $pet->name,
                'species' => $attributes['species'] ?? $pet->species,
                'breed' => $attributes['breed'] ?? $pet->breed,
                'sex' => $attributes['sex'] ?? ($attributes['gender'] ?? $pet->sex),
                'gender' => $attributes['gender'] ?? ($attributes['sex'] ?? $pet->gender),
                'size' => $attributes['size'] ?? $pet->size,
                'birth_date' => $birthdate ?? $pet->birth_date,
                'date_of_birth' => $birthdate ?? $pet->date_of_birth,
                'age_text' => $attributes['age_text'] ?? $pet->age_text,
                'bio' => $bio,
                'bio_html' => $bio ? $this->contentService->process($bio) : null,
                'personality_tags' => $tags,
                'is_public' => array_key_exists('is_public', $attributes) ? (bool) $attributes['is_public'] : $pet->is_public,
                'is_deceased' => array_key_exists('is_deceased', $attributes) ? (bool) $attributes['is_deceased'] : $pet->is_deceased,
                'is_adoptable' => array_key_exists('is_adoptable', $attributes) ? (bool) $attributes['is_adoptable'] : $pet->is_adoptable,
            ]);

            $pet->save();

            if (array_key_exists('personality_tags', $attributes)) {
                $this->syncTags($pet, $tags);
            }

            if ($avatar instanceof UploadedFile) {
                $pet->clearMediaCollection('avatar');
                $pet->addMedia($avatar)->toMediaCollection('avatar');
            }

            foreach ($galleryPhotos as $photo) {
                if ($photo instanceof UploadedFile) {
                    $pet->addMedia($photo)->toMediaCollection('gallery');
                }
            }

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
        $slugs = collect($tags)
            ->map(static fn (string $tag): array => ['slug' => Str::slug($tag), 'name' => $tag])
            ->filter(static fn (array $tag): bool => $tag['slug'] !== '')
            ->values();

        PetTag::query()
            ->where('pet_id', $pet->getKey())
            ->whereNotIn('slug', $slugs->pluck('slug'))
            ->delete();

        foreach ($slugs as $tag) {
            PetTag::query()->updateOrCreate([
                'pet_id' => $pet->getKey(),
                'slug' => $tag['slug'],
            ], [
                'name' => $tag['name'],
            ]);
        }
    }
}
