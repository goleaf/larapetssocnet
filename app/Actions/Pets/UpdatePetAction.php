<?php

namespace App\Actions\Pets;

use App\Models\Pets\Pet;
use App\Services\ContentService;
use App\Services\PersonalityTagService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdatePetAction
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly PersonalityTagService $personalityTags,
        private readonly UploadPetGalleryPhotosAction $uploadGallery,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $galleryPhotos
     */
    public function handle(Pet $pet, array $attributes, ?UploadedFile $avatar = null, array $galleryPhotos = []): Pet
    {
        return DB::transaction(function () use ($pet, $attributes, $avatar, $galleryPhotos): Pet {
            $bio = array_key_exists('bio', $attributes) ? (string) ($attributes['bio'] ?? '') : $pet->getAttribute('bio');
            $birthdate = $attributes['birthdate'] ?? $attributes['date_of_birth'] ?? $attributes['birth_date'] ?? null;
            $currentTags = $pet->getAttribute('personality_tags');
            $tags = array_key_exists('personality_tags', $attributes)
                ? $this->personalityTags->normalize($attributes['personality_tags'])
                : (is_array($currentTags) ? $currentTags : []);

            $pet->fill([
                'name' => $attributes['name'] ?? $pet->getAttribute('name'),
                'species' => $attributes['species'] ?? $pet->getAttribute('species'),
                'breed' => $attributes['breed'] ?? $pet->getAttribute('breed'),
                'sex' => $attributes['sex'] ?? ($attributes['gender'] ?? $pet->getAttribute('sex')),
                'gender' => $attributes['gender'] ?? ($attributes['sex'] ?? $pet->getAttribute('gender')),
                'size' => $attributes['size'] ?? $pet->getAttribute('size'),
                'birth_date' => $birthdate ?? $pet->getAttribute('birth_date'),
                'date_of_birth' => $birthdate ?? $pet->getAttribute('date_of_birth'),
                'age_text' => $attributes['age_text'] ?? $pet->getAttribute('age_text'),
                'bio' => $bio,
                'bio_html' => $bio ? $this->contentService->process($bio) : null,
                'personality_tags' => $tags,
                'is_public' => array_key_exists('is_public', $attributes) ? (bool) $attributes['is_public'] : (bool) $pet->getAttribute('is_public'),
                'is_deceased' => array_key_exists('is_deceased', $attributes) ? (bool) $attributes['is_deceased'] : (bool) $pet->getAttribute('is_deceased'),
                'is_adoptable' => array_key_exists('is_adoptable', $attributes) ? (bool) $attributes['is_adoptable'] : (bool) $pet->getAttribute('is_adoptable'),
            ]);

            $pet->save();

            if (array_key_exists('personality_tags', $attributes)) {
                $this->personalityTags->syncTagRecords($pet, $tags);
            }

            if ($avatar instanceof UploadedFile) {
                $pet->clearMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
                $pet->addMedia($avatar)->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
            }

            if ($galleryPhotos !== []) {
                $this->uploadGallery->handle($pet, $galleryPhotos, 'gallery_photos');
            }

            return $pet->refresh();
        });
    }
}
