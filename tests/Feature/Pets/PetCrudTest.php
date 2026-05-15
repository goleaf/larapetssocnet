<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a pet profile', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('pets.store'), [
            'name' => 'Kira',
            'species' => 'dog',
            'breed' => 'Beagle',
            'sex' => 'female',
            'birth_date' => now()->subYears(3)->toDateString(),
            'bio' => 'Friendly and playful.',
            'is_public' => '1',
            'is_adoptable' => '1',
            'personality_tags' => 'playful, friendly',
        ])
        ->assertRedirect();

    $pet = Pet::query()->where('name', 'Kira')->firstOrFail();

    expect($pet->slug)->toStartWith('kira');

    $this->assertDatabaseHas('pets', [
        'id' => $pet->getKey(),
        'user_id' => $owner->getKey(),
        'name' => 'Kira',
        'species' => 'dog',
        'breed' => 'Beagle',
        'is_public' => 1,
        'is_adoptable' => 1,
    ]);
});

it('updates a pet profile', function (): void {
    $owner = User::factory()->create();

    $pet = Pet::factory()->for($owner)->create([
        'name' => 'Milo',
        'species' => 'dog',
        'breed' => 'Mixed',
    ]);

    $this->actingAs($owner)
        ->patch(route('pets.update', $pet), [
            'name' => 'Milo Updated',
            'species' => 'dog',
            'breed' => 'Labrador',
            'sex' => 'male',
            'bio' => 'Updated bio',
            'is_public' => '1',
            'is_adoptable' => '0',
            'personality_tags' => 'loyal, curious',
        ])
        ->assertRedirect(route('pets.show', $pet->fresh()));

    $this->assertDatabaseHas('pets', [
        'id' => $pet->getKey(),
        'name' => 'Milo Updated',
        'breed' => 'Labrador',
        'bio' => 'Updated bio',
    ]);
});

it('soft deletes and restores a pet profile', function (): void {
    $owner = User::factory()->create();

    $pet = Pet::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->delete(route('pets.destroy', $pet))
        ->assertRedirect(route('pets.index'));

    $this->assertSoftDeleted('pets', [
        'id' => $pet->getKey(),
    ]);

    $trashedPet = Pet::query()->withTrashed()->findOrFail($pet->getKey());

    expect($owner->can('restore', $trashedPet))->toBeTrue();

    $trashedPet->restore();

    expect($trashedPet->fresh()->trashed())->toBeFalse();
});
