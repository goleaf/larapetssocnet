<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('allows the owner to upload gallery photos', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'is_public' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('pets.gallery.store', $pet), [
            'photos' => [
                UploadedFile::fake()->image('pet-1.jpg', 800, 600),
                UploadedFile::fake()->image('pet-2.jpg', 800, 600),
            ],
        ])
        ->assertRedirect();

    expect($pet->fresh()->galleryMedia()->count())->toBe(2);
});

it('prevents non-owners from uploading gallery photos', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $this->actingAs($viewer)
        ->post(route('pets.gallery.store', $pet), [
            'photos' => [UploadedFile::fake()->image('pet-1.jpg')],
        ])
        ->assertForbidden();
});

it('allows the owner to delete a gallery photo without touching the avatar', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $pet->addMedia(UploadedFile::fake()->image('avatar.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);

    $galleryMedia = $pet->addMedia(UploadedFile::fake()->image('gallery.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);

    $this->actingAs($owner)
        ->delete(route('pets.gallery.destroy', ['pet' => $pet, 'media' => $galleryMedia]))
        ->assertRedirect();

    $pet->refresh();

    expect($pet->getFirstMedia(Pet::MEDIA_COLLECTION_AVATAR))->not->toBeNull();
    expect($pet->getMedia(Pet::MEDIA_COLLECTION_GALLERY))->toHaveCount(0);
});

it('keeps gallery photos when updating the avatar', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'is_public' => true,
    ]);

    $pet->addMedia(UploadedFile::fake()->image('gallery.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);

    $this->actingAs($owner)
        ->patch(route('pets.update', $pet), [
            'name' => $pet->name,
            'species' => $pet->species,
            'avatar' => UploadedFile::fake()->image('new-avatar.jpg'),
        ])
        ->assertRedirect();

    expect($pet->fresh()->getMedia(Pet::MEDIA_COLLECTION_GALLERY))->toHaveCount(1);
});

it('prevents non-owners from deleting gallery photos', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $galleryMedia = $pet->addMedia(UploadedFile::fake()->image('gallery.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);

    $this->actingAs($viewer)
        ->delete(route('pets.gallery.destroy', ['pet' => $pet, 'media' => $galleryMedia]))
        ->assertForbidden();
});

it('handles deleting the same gallery photo twice safely', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $galleryMedia = $pet->addMedia(UploadedFile::fake()->image('gallery.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);

    $this->actingAs($owner)
        ->delete(route('pets.gallery.destroy', ['pet' => $pet, 'media' => $galleryMedia]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->delete(route('pets.gallery.destroy', ['pet' => $pet, 'media' => $galleryMedia->id]))
        ->assertNotFound();
});

it('reorders gallery photos for the owner', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $mediaOne = $pet->addMedia(UploadedFile::fake()->image('one.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);
    $mediaTwo = $pet->addMedia(UploadedFile::fake()->image('two.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);
    $mediaThree = $pet->addMedia(UploadedFile::fake()->image('three.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);

    $newOrder = [$mediaThree->id, $mediaOne->id, $mediaTwo->id];

    $this->actingAs($owner)
        ->patch(route('pets.gallery.reorder', $pet), [
            'order' => $newOrder,
        ])
        ->assertRedirect();

    $orderedIds = $pet->galleryMedia()->orderBy('order_column')->pluck('id')->all();

    expect($orderedIds)->toBe($newOrder);
});

it('rejects invalid gallery reorder payloads', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $otherPet = Pet::factory()->for($owner)->create();

    $mediaOne = $pet->addMedia(UploadedFile::fake()->image('one.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);
    $mediaTwo = $otherPet->addMedia(UploadedFile::fake()->image('two.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);

    $this->actingAs($owner)
        ->from(route('pets.edit', $pet))
        ->patch(route('pets.gallery.reorder', $pet), [
            'order' => [$mediaOne->id, $mediaTwo->id],
        ])
        ->assertSessionHasErrors(['order']);
});

it('rejects invalid gallery uploads', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->from(route('pets.edit', $pet))
        ->post(route('pets.gallery.store', $pet), [
            'photos' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
        ])
        ->assertSessionHasErrors(['photos.0']);
});

it('enforces max upload counts for gallery uploads', function (): void {
    Storage::fake('public');
    config(['pets.gallery.max_upload' => 2]);

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $photos = [
        UploadedFile::fake()->image('one.jpg'),
        UploadedFile::fake()->image('two.jpg'),
        UploadedFile::fake()->image('three.jpg'),
    ];

    $this->actingAs($owner)
        ->from(route('pets.edit', $pet))
        ->post(route('pets.gallery.store', $pet), [
            'photos' => $photos,
        ])
        ->assertSessionHasErrors(['photos']);
});

it('blocks viewers with a blocking relationship from seeing pet galleries', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'is_public' => true,
    ]);

    $owner->block($viewer);

    $this->actingAs($viewer)
        ->get(route('pets.show', ['pet' => $pet, 'tab' => 'gallery']))
        ->assertForbidden();
});

it('shows public gallery photos to authenticated viewers', function (): void {
    Storage::fake('public');

    $owner = User::factory()->create([
        'is_private' => false,
    ]);
    $pet = Pet::factory()->for($owner)->create([
        'is_public' => true,
    ]);

    $pet->addMedia(UploadedFile::fake()->image('gallery.jpg'))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_GALLERY);

    $this->actingAs(User::factory()->create())
        ->get(route('pets.show', ['pet' => $pet, 'tab' => 'gallery']))
        ->assertOk()
        ->assertSee('<img', false);
});
