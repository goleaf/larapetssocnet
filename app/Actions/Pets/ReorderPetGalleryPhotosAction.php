<?php

namespace App\Actions\Pets;

use App\Models\Pet;
use App\Services\PetGalleryService;

class ReorderPetGalleryPhotosAction
{
    public function __construct(private PetGalleryService $galleryService) {}

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function handle(Pet $pet, array $orderedIds): void
    {
        $this->galleryService->reorder($pet, $orderedIds);
    }
}
