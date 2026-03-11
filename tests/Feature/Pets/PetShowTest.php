<?php

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

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
