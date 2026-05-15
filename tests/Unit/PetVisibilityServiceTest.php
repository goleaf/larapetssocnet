<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetVisibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('denies private pet to strangers', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => false]);

    $service = app(PetVisibilityService::class);

    expect($service->canView($stranger, $pet))->toBeFalse();
});

it('allows owner to view private pet', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => false]);

    $service = app(PetVisibilityService::class);

    expect($service->canView($owner, $pet))->toBeTrue();
});

it('enforces followers only pets visibility', function (): void {
    $owner = User::factory()->create(['pets_visibility' => 'followers_only', 'is_private' => false]);
    $follower = User::factory()->create();
    $stranger = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $follower->follow($owner);

    $service = app(PetVisibilityService::class);

    expect($service->canView($follower, $pet))->toBeTrue()
        ->and($service->canView($stranger, $pet))->toBeFalse();
});

it('scope visibleTo hides private pets from guests', function (): void {
    $owner = User::factory()->create(['is_private' => false]);
    $publicPet = Pet::factory()->for($owner)->create(['is_public' => true]);
    $privatePet = Pet::factory()->for($owner)->create(['is_public' => false]);

    $visibleIds = Pet::query()
        ->visibleTo(null)
        ->pluck('id')
        ->all();

    expect($visibleIds)->toContain($publicPet->id)
        ->and($visibleIds)->not()->toContain($privatePet->id);
});
