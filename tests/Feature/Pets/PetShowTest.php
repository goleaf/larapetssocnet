<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 200 for a public pet profile', function (): void {
    $pet = Pet::factory()
        ->for(User::factory())
        ->create([
            'name' => 'Mochi',
            'is_public' => true,
        ]);

    $this->get(route('pets.show', $pet))
        ->assertSuccessful()
        ->assertSee('Mochi');
});

it('returns 403 for a private pet profile when viewer is not authorized', function (): void {
    $pet = Pet::factory()
        ->for(User::factory())
        ->create([
            'is_public' => false,
        ]);

    $this->get(route('pets.show', $pet))
        ->assertForbidden();
});

it('denies access when viewer is blocked by owner', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $owner->block($viewer);

    $this->actingAs($viewer)
        ->get(route('pets.show', $pet))
        ->assertForbidden();
});
