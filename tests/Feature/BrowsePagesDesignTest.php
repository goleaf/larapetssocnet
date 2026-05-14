<?php

use App\Models\Pet;
use App\Models\User;
use Database\Seeders\AdoptablePetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('renders simplified public browse pages', function (): void {
    $this->get(route('marketplace.index'))
        ->assertSuccessful()
        ->assertSee('Browse listings and contact sellers directly.');

    $this->get(route('events.index'))
        ->assertSuccessful()
        ->assertSee('Find and join upcoming pet community events.');

    $this->get(route('pets.explore'))
        ->assertSuccessful()
        ->assertSee('Discover pet profiles across the community.');

    $this->get(route('pets.adopt'))
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

    $this->get(route('pets.adopt'))
        ->assertSuccessful()
        ->assertSee('Luna')
        ->assertSee('Milo');
});
