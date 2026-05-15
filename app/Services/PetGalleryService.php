<?php

namespace App\Services;

use App\Models\Pets\Pet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PetGalleryService
{
    /**
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, Media>
     */
    public function upload(Pet $pet, array $files, string $errorKey = 'photos'): Collection
    {
        $uploads = collect($files)
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values();

        if ($uploads->isEmpty()) {
            return collect();
        }

        $maxAllowed = $this->maxPhotos();
        $currentCount = $this->galleryCount($pet);
        $incomingCount = $uploads->count();

        if ($currentCount + $incomingCount > $maxAllowed) {
            throw ValidationException::withMessages([
                $errorKey => [__('pets.validation.gallery_max', ['max' => $maxAllowed])],
            ]);
        }

        return DB::transaction(function () use ($pet, $uploads): Collection {
            return $uploads->map(function (UploadedFile $file) use ($pet): Media {
                return $pet->addMedia($file)->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);
            });
        });
    }

    public function delete(Pet $pet, Media $media): void
    {
        if (! $this->belongsToGallery($pet, $media)) {
            throw ValidationException::withMessages([
                'photo' => [__('pets.validation.gallery_not_found')],
            ]);
        }

        DB::transaction(function () use ($pet, $media): void {
            $media->delete();
            $this->normalizeOrder($pet);
        });
    }

    /**
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorder(Pet $pet, array $orderedIds): void
    {
        $orderedIds = collect($orderedIds)
            ->map(fn (int|string $id): int => (int) $id)
            ->filter()
            ->values()
            ->all();

        $galleryIds = $this->galleryIds($pet);

        if (count($orderedIds) !== count($galleryIds) || array_diff($galleryIds, $orderedIds) !== []) {
            throw ValidationException::withMessages([
                'order' => [__('pets.validation.gallery_reorder_invalid')],
            ]);
        }

        Media::setNewOrder($orderedIds);
    }

    public function updateMeta(Pet $pet, Media $media, ?string $caption, ?string $altText): Media
    {
        if (! $this->belongsToGallery($pet, $media)) {
            throw ValidationException::withMessages([
                'photo' => [__('pets.validation.gallery_not_found')],
            ]);
        }

        $normalizedCaption = $this->normalizeMeta($caption);
        $normalizedAlt = $this->normalizeMeta($altText);

        $media->setCustomProperty('caption', $normalizedCaption);
        $media->setCustomProperty('alt_text', $normalizedAlt);
        $media->save();

        return $media->refresh();
    }

    public function galleryCount(Pet $pet): int
    {
        return (int) $pet->galleryMedia()->count();
    }

    public function maxPhotos(): int
    {
        return (int) config('pets.gallery.max_photos', 30);
    }

    public function maxUploadCount(): int
    {
        return (int) config('pets.gallery.max_upload', 5);
    }

    /**
     * @return array<int, int>
     */
    public function galleryIds(Pet $pet): array
    {
        return $pet->galleryMedia()
            ->orderBy('order_column')
            ->pluck('id')
            ->map(fn (int $id): int => $id)
            ->all();
    }

    public function normalizeOrder(Pet $pet): void
    {
        $ids = $this->galleryIds($pet);

        if ($ids === []) {
            return;
        }

        Media::setNewOrder($ids);
    }

    private function belongsToGallery(Pet $pet, Media $media): bool
    {
        return $media->collection_name === Pet::MEDIA_COLLECTION_GALLERY
            && $media->model_type === $pet->getMorphClass()
            && (int) $media->model_id === (int) $pet->getKey();
    }

    private function normalizeMeta(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cleaned = Str::squish($value);

        return $cleaned === '' ? null : $cleaned;
    }
}
