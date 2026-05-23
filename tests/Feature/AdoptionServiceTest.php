<?php

use App\Models\Identity\User;
use App\Models\Marketplace\MarketplaceListing;
use App\Models\Pets\Pet;
use App\Services\AdoptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new AdoptionService;
    $this->user = User::factory()->create();
});

it('allows valid transition from not_listed to available', function (): void {
    $pet = Pet::factory()->create([
        'user_id' => $this->user->id,
        'adoption_status' => 'not_listed',
    ]);

    $this->service->setStatus($pet, 'available', ['fee' => 50, 'contact' => 'test@example.com']);

    $pet->refresh();
    expect($pet->adoption_status)->toBe('available');
    expect($pet->adoption_fee)->toBe(50);
    expect($pet->adoption_contact)->toBe('test@example.com');
    expect($pet->adoption_listed_at)->not->toBeNull();
    expect((bool) $pet->is_adoptable)->toBeTrue();

    $this->assertDatabaseHas('marketplace_listings', [
        'pet_id' => $pet->getKey(),
        'user_id' => $this->user->getKey(),
        'listing_type' => 'adoption',
        'status' => MarketplaceListing::STATUS_ACTIVE,
        'contact_email' => 'test@example.com',
    ]);
});

it('allows valid transition from available to pending', function (): void {
    $pet = Pet::factory()->create([
        'user_id' => $this->user->id,
        'adoption_status' => 'available',
    ]);

    $this->service->setStatus($pet, 'pending');

    expect($pet->fresh()->adoption_status)->toBe('pending');
});

it('allows valid transition from pending to adopted', function (): void {
    $pet = Pet::factory()->create([
        'user_id' => $this->user->id,
        'adoption_status' => 'pending',
    ]);

    $this->service->setStatus($pet, 'adopted');

    expect($pet->fresh()->adoption_status)->toBe('adopted');
});

it('rejects invalid transition', function (): void {
    $pet = Pet::factory()->create([
        'user_id' => $this->user->id,
        'adoption_status' => 'not_listed',
    ]);

    $this->service->setStatus($pet, 'adopted');
})->throws(RuntimeException::class, 'Invalid adoption transition');

it('allows transition from adopted back to not_listed', function (): void {
    $pet = Pet::factory()->create([
        'user_id' => $this->user->id,
        'adoption_status' => 'adopted',
    ]);

    $this->service->setStatus($pet, 'not_listed');

    expect($pet->fresh()->adoption_status)->toBe('not_listed');
});

it('archives and soft deletes the adoption listing when a pet is unlisted', function (): void {
    $pet = Pet::factory()->create([
        'user_id' => $this->user->id,
        'adoption_status' => 'available',
        'is_adoptable' => true,
    ]);

    $listing = MarketplaceListing::factory()->for($this->user, 'seller')->for($pet)->create([
        'listing_type' => 'adoption',
        'status' => MarketplaceListing::STATUS_ACTIVE,
    ]);

    $this->service->setStatus($pet, 'not_listed');

    expect((bool) $pet->fresh()->is_adoptable)->toBeFalse();

    $this->assertSoftDeleted('marketplace_listings', [
        'id' => $listing->getKey(),
        'status' => MarketplaceListing::STATUS_ARCHIVED,
    ]);
});
