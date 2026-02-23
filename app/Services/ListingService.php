<?php

namespace App\Services;

use App\Models\MarketplaceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ListingService
{
    private const MAX_IMAGES = 8;

    /**
     * @var list<string>
     */
    private const ALLOWED_STATUSES = [
        MarketplaceListing::STATUS_DRAFT,
        MarketplaceListing::STATUS_ACTIVE,
        MarketplaceListing::STATUS_SOLD,
        MarketplaceListing::STATUS_ARCHIVED,
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $owner, array $attributes): MarketplaceListing
    {
        /** @var list<UploadedFile> $images */
        $images = $this->normalizeImages($attributes['images'] ?? []);
        $coverImageIndex = isset($attributes['cover_image_index']) ? (int) $attributes['cover_image_index'] : null;

        unset($attributes['images'], $attributes['cover_image_index'], $attributes['cover_image_id'], $attributes['remove_cover_image'], $attributes['replace_gallery']);

        $attributes['currency'] = strtoupper((string) ($attributes['currency'] ?? 'USD'));

        return DB::transaction(function () use ($owner, $attributes, $images, $coverImageIndex): MarketplaceListing {
            $listing = MarketplaceListing::query()->create([
                ...$attributes,
                'user_id' => $owner->getKey(),
            ]);

            if ($images !== []) {
                $this->uploadImages($listing, $images, $coverImageIndex);
            }

            return $listing->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(MarketplaceListing $listing, array $attributes): MarketplaceListing
    {
        /** @var list<UploadedFile> $images */
        $images = $this->normalizeImages($attributes['images'] ?? []);
        $coverImageIndex = isset($attributes['cover_image_index']) ? (int) $attributes['cover_image_index'] : null;
        $coverImageId = isset($attributes['cover_image_id']) ? (int) $attributes['cover_image_id'] : null;
        $removeCoverImage = (bool) ($attributes['remove_cover_image'] ?? false);
        $replaceGallery = (bool) ($attributes['replace_gallery'] ?? false);

        unset($attributes['images'], $attributes['cover_image_index'], $attributes['cover_image_id'], $attributes['remove_cover_image'], $attributes['replace_gallery']);

        if (array_key_exists('currency', $attributes)) {
            $attributes['currency'] = strtoupper((string) ($attributes['currency'] ?: 'USD'));
        }

        return DB::transaction(function () use ($listing, $attributes, $images, $coverImageIndex, $coverImageId, $removeCoverImage, $replaceGallery): MarketplaceListing {
            if ($attributes !== []) {
                $listing->update($attributes);
            }

            if ($removeCoverImage) {
                $this->clearCoverImage($listing);
            }

            if ($replaceGallery) {
                $this->clearGalleryImages($listing);
            }

            if ($coverImageId !== null) {
                $this->setCoverByMediaId($listing, $coverImageId);
            }

            if ($images !== []) {
                $this->uploadImages($listing, $images, $coverImageIndex);
            }

            $this->ensureCoverImage($listing);

            return $listing->fresh();
        });
    }

    /**
     * @param  list<UploadedFile>  $images
     */
    public function uploadImages(MarketplaceListing $listing, array $images, ?int $coverImageIndex = null): MarketplaceListing
    {
        $images = $this->normalizeImages($images);

        if ($images === []) {
            return $listing;
        }

        $existingCount = $this->imageCount($listing);
        $incomingCount = count($images);

        if (($existingCount + $incomingCount) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => ['A listing can have at most '.self::MAX_IMAGES.' images.'],
            ]);
        }

        /** @var array<int, Media> $uploadedMedia */
        $uploadedMedia = [];

        foreach ($images as $image) {
            $storedPath = $image->store('listings/'.$listing->getKey(), 'public');

            $uploadedMedia[] = $listing
                ->addMediaFromDisk($storedPath, 'public')
                ->withCustomProperties(['source_path' => $storedPath])
                ->toMediaCollection('gallery', 'public');
        }

        $targetCover = null;

        if ($coverImageIndex !== null && array_key_exists($coverImageIndex, $uploadedMedia)) {
            $targetCover = $uploadedMedia[$coverImageIndex];
        }

        if (! $targetCover && $listing->getFirstMedia('cover') === null) {
            $targetCover = $uploadedMedia[0] ?? null;
        }

        if ($targetCover !== null) {
            $this->moveToCover($listing, $targetCover);
        }

        return $listing->fresh();
    }

    public function deleteImage(MarketplaceListing $listing, int $imageId): MarketplaceListing
    {
        /** @var Media|null $media */
        $media = $listing->media()
            ->whereKey($imageId)
            ->whereIn('collection_name', ['cover', 'gallery', 'images'])
            ->first();

        if (! $media) {
            throw (new ModelNotFoundException)->setModel(Media::class, [$imageId]);
        }

        $wasCover = $media->collection_name === 'cover';
        $this->deleteStoredSource($media);
        $media->delete();

        if ($wasCover) {
            $this->ensureCoverImage($listing->fresh());
        }

        return $listing->fresh();
    }

    public function changeStatus(MarketplaceListing $listing, string $status): MarketplaceListing
    {
        $normalizedStatus = strtolower(trim($status));

        if (! in_array($normalizedStatus, self::ALLOWED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['Invalid listing status.'],
            ]);
        }

        $listing->update(['status' => $normalizedStatus]);

        return $listing->fresh();
    }

    public function softDelete(MarketplaceListing $listing): void
    {
        $listing->delete();
    }

    public function restore(MarketplaceListing $listing): MarketplaceListing
    {
        if (method_exists($listing, 'trashed') && $listing->trashed()) {
            $listing->restore();
        }

        return $listing->fresh();
    }

    private function imageCount(MarketplaceListing $listing): int
    {
        return $listing->getMedia('cover')->count()
            + $listing->getMedia('gallery')->count()
            + $listing->getMedia('images')->count();
    }

    private function clearCoverImage(MarketplaceListing $listing): void
    {
        foreach ($listing->getMedia('cover') as $media) {
            $this->deleteStoredSource($media);
        }

        $listing->clearMediaCollection('cover');
    }

    private function clearGalleryImages(MarketplaceListing $listing): void
    {
        foreach ($listing->getMedia('gallery') as $media) {
            $this->deleteStoredSource($media);
        }

        foreach ($listing->getMedia('images') as $media) {
            $this->deleteStoredSource($media);
        }

        $listing->clearMediaCollection('gallery');
        $listing->clearMediaCollection('images');
    }

    private function setCoverByMediaId(MarketplaceListing $listing, int $coverImageId): void
    {
        /** @var Media|null $media */
        $media = $listing->media()
            ->whereKey($coverImageId)
            ->whereIn('collection_name', ['cover', 'gallery', 'images'])
            ->first();

        if (! $media) {
            throw ValidationException::withMessages([
                'cover_image_id' => ['Selected cover image is invalid.'],
            ]);
        }

        $this->moveToCover($listing, $media);
    }

    private function ensureCoverImage(MarketplaceListing $listing): void
    {
        if ($listing->getFirstMedia('cover') !== null) {
            return;
        }

        $fallback = $listing->getMedia('gallery')->first() ?? $listing->getMedia('images')->first();

        if ($fallback === null) {
            return;
        }

        $this->moveToCover($listing, $fallback);
    }

    private function moveToCover(MarketplaceListing $listing, Media $media): void
    {
        if ($media->collection_name === 'cover') {
            return;
        }

        $listing->clearMediaCollection('cover');

        if (method_exists($media, 'move')) {
            $media->move($listing, 'cover', 'public');

            return;
        }

        $sourcePath = data_get($media->custom_properties, 'source_path');

        if (is_string($sourcePath) && Storage::disk('public')->exists($sourcePath)) {
            $listing
                ->addMediaFromDisk($sourcePath, 'public')
                ->withCustomProperties(['source_path' => $sourcePath])
                ->toMediaCollection('cover', 'public');
            $media->delete();

            return;
        }

        $listing->addMedia($media->getPath())->toMediaCollection('cover', 'public');
        $media->delete();
    }

    private function deleteStoredSource(Media $media): void
    {
        $sourcePath = data_get($media->custom_properties, 'source_path');

        if (! is_string($sourcePath) || $sourcePath === '') {
            return;
        }

        Storage::disk('public')->delete($sourcePath);
    }

    /**
     * @return list<UploadedFile>
     */
    private function normalizeImages(mixed $images): array
    {
        if ($images instanceof UploadedFile) {
            return [$images];
        }

        if (! is_array($images)) {
            return [];
        }

        return array_values(array_filter($images, static fn (mixed $file): bool => $file instanceof UploadedFile));
    }
}
