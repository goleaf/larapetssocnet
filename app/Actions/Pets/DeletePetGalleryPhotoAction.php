<?php

declare(strict_types=1);

namespace App\Actions\Pets;

use App\Models\Pets\Pet;
use App\Services\PetGalleryService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DeletePetGalleryPhotoAction
{
    public function __construct(private PetGalleryService $galleryService) {}

    public function handle(Pet $pet, Media $media): void
    {
        $this->galleryService->delete($pet, $media);
    }
}
