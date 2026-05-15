<?php

declare(strict_types=1);

namespace App\Actions\Pets;

use App\Models\Pets\Pet;
use App\Services\PetGalleryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UploadPetGalleryPhotosAction
{
    public function __construct(private PetGalleryService $galleryService) {}

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return Collection<int, Media>
     */
    public function handle(Pet $pet, array $photos, string $errorKey = 'photos'): Collection
    {
        return $this->galleryService->upload($pet, $photos, $errorKey);
    }
}
