<?php

use App\Models\Breed;
use App\Models\Pet;
use App\Models\PetTag;
use App\Models\Species;
use App\Models\User;
use App\Services\PersonalityTagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('applies the public scope', function (): void {
    $publicPet = Pet::factory()->create(['is_public' => true]);
    $privatePet = Pet::factory()->create(['is_public' => false]);

    $petIds = Pet::query()
        ->public()
        ->pluck('pets.id');

    expect($petIds)
        ->toContain($publicPet->getKey())
        ->not->toContain($privatePet->getKey());
});

it('defaults to not adoptable', function (): void {
    $pet = Pet::factory()->create();

    expect((bool) $pet->is_adoptable)->toBeFalse();
});

it('applies the ownedBy scope', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $ownedPet = Pet::factory()->create(['user_id' => $owner->getKey()]);
    $otherPet = Pet::factory()->create(['user_id' => $otherOwner->getKey()]);

    $petIds = Pet::query()
        ->ownedBy($owner->getKey())
        ->pluck('pets.id');

    expect($petIds)
        ->toContain($ownedPet->getKey())
        ->not->toContain($otherPet->getKey());
});

it('applies the adoptable scope', function (): void {
    $adoptable = Pet::factory()->create(['is_adoptable' => true]);
    $notAdoptable = Pet::factory()->create(['is_adoptable' => false]);

    $petIds = Pet::query()
        ->adoptable()
        ->pluck('pets.id');

    expect($petIds)
        ->toContain($adoptable->getKey())
        ->not->toContain($notAdoptable->getKey());
});

it('applies species and breed scopes', function (): void {
    $dog = Pet::factory()->create(['species' => 'dog', 'breed' => 'beagle']);
    $cat = Pet::factory()->create(['species' => 'cat', 'breed' => 'siamese']);

    $speciesIds = Pet::query()->bySpecies('dog')->pluck('pets.id');
    $breedIds = Pet::query()->byBreed('beagle')->pluck('pets.id');

    expect($speciesIds)
        ->toContain($dog->getKey())
        ->not->toContain($cat->getKey());

    expect($breedIds)
        ->toContain($dog->getKey())
        ->not->toContain($cat->getKey());
});

it('applies personality tag scopes', function (): void {
    $service = app(PersonalityTagService::class);

    $playful = Pet::factory()->create(['name' => 'Playful']);
    $calm = Pet::factory()->create(['name' => 'Calm']);

    $service->sync($playful, ['playful', 'gentle']);
    $service->sync($calm, ['calm']);

    $tagIds = Pet::query()
        ->withPersonalityTag('playful')
        ->pluck('pets.id');

    expect($tagIds)
        ->toContain($playful->getKey())
        ->not->toContain($calm->getKey());

    $anyTagIds = Pet::query()
        ->withAnyPersonalityTags(['gentle', 'calm'])
        ->pluck('pets.id');

    expect($anyTagIds)
        ->toContain($playful->getKey())
        ->toContain($calm->getKey());
});

it('generates slug on create via observer', function (): void {
    $pet = Pet::factory()->create([
        'name' => 'Captain Whiskers',
        'slug' => null,
    ]);

    expect($pet->slug)->toStartWith('captain-whiskers');
});

it('defines required relationships and route key configuration', function (): void {
    $pet = Pet::factory()->create();

    expect($pet->getRouteKeyName())->toBe('slug');
    expect($pet->getCasts())->toHaveKey('birthdate');
    expect($pet->getCasts())->toHaveKey('is_public');

    expect($pet->user()->getRelated())->toBeInstanceOf(User::class);
    expect($pet->species()->getRelated())->toBeInstanceOf(Species::class);
    expect($pet->breed()->getRelated())->toBeInstanceOf(Breed::class);
    expect($pet->tags()->getRelated())->toBeInstanceOf(PetTag::class);
});
