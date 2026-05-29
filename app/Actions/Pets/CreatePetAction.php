<?php

namespace App\Actions\Pets;

use App\Models\Identity\User;
use App\Models\Pets\Breed;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwner;
use App\Models\Pets\Species;
use App\Services\AdoptionService;
use App\Services\ContentService;
use App\Services\PersonalityTagService;
use App\Services\PetSlugService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePetAction
{
    public function __construct(
        private readonly ContentService $contentService,
        private readonly PersonalityTagService $personalityTags,
        private readonly UploadPetGalleryPhotosAction $uploadGallery,
        private readonly PetSlugService $slugService,
        private readonly AdoptionService $adoptionService,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $galleryPhotos
     */
    public function handle(User $owner, array $attributes, ?UploadedFile $avatar = null, array $galleryPhotos = [], ?UploadedFile $cover = null): Pet
    {
        return DB::transaction(function () use ($owner, $attributes, $avatar, $galleryPhotos, $cover): Pet {
            $bio = isset($attributes['bio']) ? (string) $attributes['bio'] : null;
            $birthdate = $attributes['birthdate'] ?? $attributes['date_of_birth'] ?? $attributes['birth_date'] ?? null;
            $birthYear = $attributes['birth_year'] ?? ($birthdate ? (int) date('Y', strtotime((string) $birthdate)) : null);
            $tags = $this->personalityTags->normalize($attributes['personality_tags'] ?? []);
            $visibility = (string) ($attributes['visibility'] ?? (((bool) ($attributes['is_public'] ?? true)) ? 'public' : 'private'));
            $species = $this->resolveSpecies($attributes);
            $breed = $this->resolveBreed($attributes, $species);
            $breedDescription = $attributes['breed_description'] ?? $attributes['breed_label'] ?? null;

            if (! in_array($visibility, Pet::VISIBILITY, true)) {
                $visibility = 'public';
            }

            $slug = null;

            if (Schema::hasColumn('pets', 'slug')) {
                $slug = $this->slugService->generateUnique(
                    (string) $attributes['name'],
                    (string) ($owner->getAttribute('username') ?? 'pet')
                );
            }

            $pet = Pet::query()->create([
                'user_id' => $owner->getKey(),
                'name' => (string) $attributes['name'],
                'slug' => $slug,
                'species' => (string) ($species?->slug ?? $attributes['species']),
                'species_id' => $species?->getKey(),
                'species_other' => $attributes['species_other'] ?? null,
                'breed' => $breed?->name ?? $attributes['breed'] ?? null,
                'breed_id' => $breed?->getKey(),
                'breed_description' => $breed ? null : $this->nullableString($breedDescription),
                'sex' => $attributes['sex'] ?? ($attributes['gender'] ?? 'unknown'),
                'gender' => $attributes['gender'] ?? ($attributes['sex'] ?? 'unknown'),
                'size' => $attributes['size'] ?? null,
                'birth_date' => $birthdate,
                'date_of_birth' => $birthdate,
                'birth_year' => $birthYear,
                'age_text' => $attributes['age_text'] ?? null,
                'bio' => $bio,
                'bio_html' => $bio ? $this->contentService->process($bio) : null,
                'personality_tags' => $tags,
                'visibility' => $visibility,
                'is_public' => $visibility === 'public',
                'is_deceased' => (bool) ($attributes['is_deceased'] ?? false),
                'spayed_neutered_status' => $attributes['spayed_neutered_status'] ?? 'unknown',
                'vaccination_status' => $attributes['vaccination_status'] ?? 'unknown',
                'last_vaccinated_on' => $attributes['last_vaccinated_on'] ?? null,
                'microchipped_status' => $attributes['microchipped_status'] ?? 'unknown',
                'microchip_number' => $attributes['microchip_number'] ?? null,
                'is_adoptable' => (bool) ($attributes['is_adoptable'] ?? false),
                'is_archived' => false,
                'weight_unit' => $attributes['weight_unit'] ?? 'kg',
                'cover_photo_position' => (float) ($attributes['cover_photo_position'] ?? 50),
            ]);

            if ($avatar instanceof UploadedFile) {
                $pet->addMedia($avatar)->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
            }

            if ($cover instanceof UploadedFile) {
                $pet->addMedia($cover)->toMediaCollection(Pet::MEDIA_COLLECTION_COVER);
            }

            $this->personalityTags->syncTagRecords($pet, $tags);

            if ($galleryPhotos !== []) {
                $this->uploadGallery->handle($pet, $galleryPhotos, 'gallery_photos');
            }

            PetOwner::query()->updateOrCreate([
                'pet_id' => $pet->getKey(),
                'user_id' => $owner->getKey(),
            ], [
                'role' => PetOwner::ROLE_OWNER,
                'is_primary_owner' => true,
                'can_post' => true,
                'can_edit' => true,
                'can_manage_health' => true,
                'can_manage_gallery' => true,
                'can_manage_adoption' => true,
                'can_delete' => true,
                'accepted_at' => now(),
            ]);

            if ((bool) ($attributes['is_adoptable'] ?? false)) {
                $this->adoptionService->setStatus($pet->refresh(), 'available', [
                    'notes' => 'Adoption listing — complete this after creating the profile.',
                    'contact' => $owner->email,
                ]);
            }

            $owner->incrementCounter('pets_count');

            return $pet->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveSpecies(array $attributes): ?Species
    {
        if (! Schema::hasTable('species')) {
            return null;
        }

        if (isset($attributes['species_id']) && (int) $attributes['species_id'] > 0) {
            return Species::query()->whereKey((int) $attributes['species_id'])->first();
        }

        $slug = (string) ($attributes['species'] ?? '');

        if ($slug === '') {
            return null;
        }

        return Species::query()->where('slug', $slug)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveBreed(array $attributes, ?Species $species): ?Breed
    {
        if (! Schema::hasTable('breeds')) {
            return null;
        }

        if (isset($attributes['breed_id']) && (int) $attributes['breed_id'] > 0) {
            return Breed::query()->whereKey((int) $attributes['breed_id'])->first();
        }

        $breed = $this->nullableString($attributes['breed'] ?? null);

        if ($breed === null) {
            return null;
        }

        $query = Breed::query()->where('name', $breed);

        if ($species instanceof Species && Schema::hasColumn('breeds', 'species_id')) {
            $query->where('species_id', $species->getKey());
        } elseif ($species instanceof Species) {
            $query->where('species_slug', $species->slug);
        }

        return $query->first();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
