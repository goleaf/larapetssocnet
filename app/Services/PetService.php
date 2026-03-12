<?php

namespace App\Services;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class PetService
{
    public function __construct(
        private ContentService $content,
        private MediaService $media,
        private PersonalityTagService $personalityTags,
        private PetGalleryService $galleryService,
    ) {}

    public function create(User $owner, array $data, ?UploadedFile $avatar = null): Pet
    {
        return DB::transaction(function () use ($owner, $data, $avatar): Pet {
            $bio = $data['bio'] ?? null;
            $bioHtml = $bio ? $this->content->process($bio) : null;

            $tags = $this->personalityTags->normalize($data['personality_tags'] ?? []);

            $pet = Pet::create([
                'user_id' => $owner->id,
                'name' => $data['name'],
                'species' => $data['species'] ?? 'other',
                'breed' => $data['breed'] ?? null,
                'gender' => $data['gender'] ?? 'unknown',
                'size' => $data['size'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'age_text' => $data['age_text'] ?? null,
                'bio' => $bio,
                'bio_html' => $bioHtml,
                'is_deceased' => $data['is_deceased'] ?? false,
                'is_public' => $data['is_public'] ?? true,
                'is_adoptable' => $data['is_adoptable'] ?? false,
                'personality_tags' => $tags,
            ]);

            if ($avatar) {
                $pet->addMedia($avatar)->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
            }

            $this->personalityTags->syncTagRecords($pet, $tags);
            $owner->increment('pets_count');

            return $pet;
        });
    }

    public function update(Pet $pet, array $data, ?UploadedFile $avatar = null): Pet
    {
        return DB::transaction(function () use ($pet, $data, $avatar): Pet {
            $bio = $data['bio'] ?? $pet->bio;
            $bioHtml = $bio ? $this->content->process($bio) : null;

            $tags = array_key_exists('personality_tags', $data)
                ? $this->personalityTags->normalize($data['personality_tags'])
                : (array) $pet->personality_tags;

            $pet->update(array_filter([
                'name' => $data['name'] ?? $pet->name,
                'species' => $data['species'] ?? $pet->species,
                'breed' => array_key_exists('breed', $data) ? $data['breed'] : $pet->breed,
                'gender' => $data['gender'] ?? $pet->gender,
                'size' => array_key_exists('size', $data) ? $data['size'] : $pet->size,
                'date_of_birth' => array_key_exists('date_of_birth', $data) ? $data['date_of_birth'] : $pet->date_of_birth,
                'age_text' => array_key_exists('age_text', $data) ? $data['age_text'] : $pet->age_text,
                'bio' => $bio,
                'bio_html' => $bioHtml,
                'is_deceased' => $data['is_deceased'] ?? $pet->is_deceased,
                'is_public' => $data['is_public'] ?? $pet->is_public,
                'is_adoptable' => $data['is_adoptable'] ?? $pet->is_adoptable,
                'personality_tags' => $tags,
            ], fn ($v) => $v !== null));

            if ($avatar) {
                $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
                $pet->addMedia($avatar)->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
            }

            if (array_key_exists('personality_tags', $data)) {
                $this->personalityTags->syncTagRecords($pet, $tags);
            }

            return $pet->fresh();
        });
    }

    public function delete(Pet $pet): void
    {
        DB::transaction(function () use ($pet): void {
            $pet->owner->decrement('pets_count');
            $pet->delete();
        });
    }

    public function addGalleryPhoto(Pet $pet, UploadedFile $file): void
    {
        $this->galleryService->upload($pet, [$file]);
    }

    public function removeGalleryPhoto(Pet $pet, int $mediaId): void
    {
        $media = $pet->galleryMedia()->whereKey($mediaId)->firstOrFail();

        $this->galleryService->delete($pet, $media);
    }
}
