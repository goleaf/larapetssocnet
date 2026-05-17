<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Database\Seeders\AdoptablePetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('renders simplified browse pages for authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('marketplace.index'))
        ->assertSuccessful()
        ->assertSee('Browse listings and contact sellers directly.');

    $this->actingAs($user)
        ->get(route('events.index'))
        ->assertSuccessful()
        ->assertSee('Find and join upcoming pet community events.');

    $this->actingAs($user)
        ->get(route('pets.explore'))
        ->assertSuccessful()
        ->assertSee('Discover pet profiles across the community.');

    $this->actingAs($user)
        ->get(route('pets.adopt'))
        ->assertSuccessful()
        ->assertSee('Browse pets currently marked as adoptable.');
});

it('renders simplified groups page for authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('groups.index'))
        ->assertSuccessful()
        ->assertSee('Find and join communities for pet lovers.');
});

it('seeds adoptable pets for adopt page browsing', function (): void {
    User::factory()->count(4)->create();

    $this->seed(AdoptablePetSeeder::class);

    $adoptableQuery = Pet::query();

    if (Schema::hasColumn('pets', 'is_adoptable')) {
        $adoptableQuery->where('is_adoptable', true);
    }

    if (Schema::hasColumn('pets', 'adoption_status')) {
        $adoptableQuery->where('adoption_status', 'available');
    }

    expect($adoptableQuery->count())->toBeGreaterThan(0);

    $this->actingAs(User::factory()->create())
        ->get(route('pets.adopt'))
        ->assertSuccessful()
        ->assertSee('Luna')
        ->assertSee('Milo');
});
