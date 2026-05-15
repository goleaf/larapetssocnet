<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows pet owner to view followers page', function (): void {
    $owner = User::factory()->create();
    $follower = User::factory()->create(['name' => 'Follower Friend']);
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $follower->followPet($pet);

    $this->actingAs($owner)
        ->get(route('pets.followers.index', $pet))
        ->assertOk()
        ->assertSee('Follower Friend');
});

it('denies non-owners from viewing pet followers page', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $this->actingAs($viewer)
        ->get(route('pets.followers.index', $pet))
        ->assertForbidden();
});
