<?php

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('adoption page accessible to guests', function (): void {
    $this->get('/adoption')->assertOk();
});

it('available pet appears on adoption page', function (): void {
    Pet::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'AdoptBuddy',
        'is_public' => true,
        'adoption_status' => 'available',
        'adoption_listed_at' => now(),
    ]);

    $this->get('/adoption')
        ->assertOk()
        ->assertSee('AdoptBuddy');
});

it('non-listed pet not on adoption page', function (): void {
    Pet::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'HiddenPet',
        'is_public' => true,
        'adoption_status' => 'not_listed',
    ]);

    $this->get('/adoption')
        ->assertOk()
        ->assertDontSee('HiddenPet');
});

it('adoption page filterable by species', function (): void {
    Pet::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'DogAdopt',
        'species' => 'dog',
        'is_public' => true,
        'adoption_status' => 'available',
        'adoption_listed_at' => now(),
    ]);

    Pet::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'CatAdopt',
        'species' => 'cat',
        'is_public' => true,
        'adoption_status' => 'available',
        'adoption_listed_at' => now(),
    ]);

    $this->get('/adoption?species=dog')
        ->assertOk()
        ->assertSee('DogAdopt')
        ->assertDontSee('CatAdopt');
});

it('non-owner cannot change adoption status', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->create([
        'user_id' => $owner->id,
        'adoption_status' => 'not_listed',
    ]);

    $this->actingAs($this->user)
        ->patchJson("/pets/{$pet->slug}/adoption", ['status' => 'available'])
        ->assertForbidden();
});
