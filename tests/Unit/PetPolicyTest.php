<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Policies\PetPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('allows owner to view and manage pet', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => false]);
    $policy = app(PetPolicy::class);

    expect($policy->view($owner, $pet))->toBeTrue()
        ->and($policy->update($owner, $pet))->toBeTrue()
        ->and($policy->delete($owner, $pet))->toBeTrue()
        ->and($policy->manageAvatar($owner, $pet))->toBeTrue();
});

it('denies strangers from updating or deleting pet', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);
    $policy = app(PetPolicy::class);

    expect($policy->update($stranger, $pet))->toBeFalse()
        ->and($policy->delete($stranger, $pet))->toBeFalse()
        ->and($policy->manageAvatar($stranger, $pet))->toBeFalse();
});

it('respects pets visibility followers only', function (): void {
    $owner = User::factory()->create(['pets_visibility' => 'everyone', 'is_private' => false]);
    $follower = User::factory()->create();
    $stranger = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'is_public' => true,
        'visibility' => 'followers_only',
    ]);
    $policy = app(PetPolicy::class);

    $follower->followPet($pet);

    expect($policy->view($follower, $pet))->toBeTrue()
        ->and($policy->viewPosts($follower, $pet))->toBeTrue()
        ->and($policy->view($stranger, $pet))->toBeTrue()
        ->and($policy->viewPosts($stranger, $pet))->toBeFalse();
});

it('denies blocked viewers', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);
    $policy = app(PetPolicy::class);

    $viewer->block($owner);

    $response = $policy->view($viewer, $pet);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('allows admin to view and update pets unless blocked', function (): void {
    Role::findOrCreate('admin');

    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $pet = Pet::factory()->for($owner)->create(['is_public' => false]);
    $policy = app(PetPolicy::class);

    expect($policy->view($admin, $pet))->toBeTrue()
        ->and($policy->update($admin, $pet))->toBeTrue();
});

it('prevents owners from following their own pets', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);
    $policy = app(PetPolicy::class);

    expect($policy->follow($owner, $pet))->toBeFalse();
});
