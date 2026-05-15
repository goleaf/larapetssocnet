<?php

use App\Actions\Pets\CreatePetAction;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a pet with correct attributes', function (): void {
    $owner = User::factory()->create();

    $pet = app(CreatePetAction::class)->handle($owner, [
        'name' => 'Biscuit',
        'species' => 'dog',
        'breed' => 'Beagle',
        'sex' => 'male',
        'birthdate' => now()->subYears(2)->toDateString(),
        'bio' => 'Loves long walks.',
        'is_public' => true,
        'is_adoptable' => true,
        'personality_tags' => 'friendly,calm',
    ]);

    expect($pet)->toBeInstanceOf(Pet::class);
    expect($pet->name)->toBe('Biscuit');
    expect($pet->species)->toBe('dog');
    expect($pet->breed)->toBe('Beagle');
    expect($pet->is_public)->toBeTrue();
    expect($pet->is_adoptable)->toBeTrue();
    expect($pet->slug)->toStartWith('biscuit');

    $this->assertDatabaseHas('pets', [
        'id' => $pet->getKey(),
        'user_id' => $owner->getKey(),
        'name' => 'Biscuit',
        'species' => 'dog',
        'breed' => 'Beagle',
    ]);
});
