<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Services\PetGalleryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class UploadPetGalleryPhotosAction
{
    public function __construct(private PetGalleryService $galleryService) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return Collection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media>
     */
    public function handle(Pet $pet, array $photos, string $errorKey = 'photos'): Collection
    {
        return $this->galleryService->upload($pet, $photos, $errorKey);
    }
}
