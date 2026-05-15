<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetGalleryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('uploads gallery photos within the configured limit', function (): void {
    Storage::fake('public');

    $pet = Pet::factory()->for(User::factory())->create();
    $service = app(PetGalleryService::class);

    $mediaItems = $service->upload($pet, [
        UploadedFile::fake()->image('one.jpg'),
        UploadedFile::fake()->image('two.jpg'),
    ]);

    expect($mediaItems)->toHaveCount(2);
    expect($pet->fresh()->galleryMedia()->count())->toBe(2);
});

it('rejects uploads that exceed the gallery max', function (): void {
    Storage::fake('public');
    config(['pets.gallery.max_photos' => 1]);

    $pet = Pet::factory()->for(User::factory())->create();
    $service = app(PetGalleryService::class);

    $service->upload($pet, [UploadedFile::fake()->image('one.jpg')]);

    expect(fn () => $service->upload($pet, [UploadedFile::fake()->image('two.jpg')]))
        ->toThrow(ValidationException::class);
});

it('updates gallery metadata safely', function (): void {
    Storage::fake('public');

    $pet = Pet::factory()->for(User::factory())->create();
    $service = app(PetGalleryService::class);

    $media = $service->upload($pet, [UploadedFile::fake()->image('one.jpg')])->firstOrFail();

    $updated = $service->updateMeta($pet, $media, 'Sleepy day', 'Pet lounging');

    expect($updated->getCustomProperty('caption'))->toBe('Sleepy day');
    expect($updated->getCustomProperty('alt_text'))->toBe('Pet lounging');
});

it('reorders gallery items deterministically', function (): void {
    Storage::fake('public');

    $pet = Pet::factory()->for(User::factory())->create();
    $service = app(PetGalleryService::class);

    $mediaOne = $service->upload($pet, [UploadedFile::fake()->image('one.jpg')])->firstOrFail();
    $mediaTwo = $service->upload($pet, [UploadedFile::fake()->image('two.jpg')])->firstOrFail();

    $service->reorder($pet, [$mediaTwo->id, $mediaOne->id]);

    $orderedIds = $pet->galleryMedia()->orderBy('order_column')->pluck('id')->all();

    expect($orderedIds)->toBe([$mediaTwo->id, $mediaOne->id]);
});
