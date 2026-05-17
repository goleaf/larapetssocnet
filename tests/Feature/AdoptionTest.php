<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('adoption page requires authentication', function (): void {
    $this->get('/adoption')->assertRedirect(route('login'));
});

it('available pet appears on adoption page', function (): void {
    Pet::factory()->create([
        'user_id' => $this->user->id,
        'name' => 'AdoptBuddy',
        'is_public' => true,
        'adoption_status' => 'available',
        'adoption_listed_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get('/adoption')
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

    $this->actingAs($this->user)
        ->get('/adoption')
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

    $this->actingAs($this->user)
        ->get('/adoption?species=dog')
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
