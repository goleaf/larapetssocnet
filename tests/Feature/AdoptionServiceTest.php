<?php

use App\Models\Pet;
use App\Models\User;
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
