<?php

use App\Models\Identity\User;
use App\Models\Pets\Breed;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns species-scoped breed suggestions ordered by name', function (): void {
    $viewer = User::factory()->create();

    Breed::query()->create(['name' => 'Labrador Retriever', 'slug' => 'labrador-retriever', 'species_slug' => 'dog']);
    Breed::query()->create(['name' => 'Lagotto Romagnolo', 'slug' => 'lagotto-romagnolo', 'species_slug' => 'dog']);
    Breed::query()->create(['name' => 'LaPerm', 'slug' => 'laperm', 'species_slug' => 'cat']);
    Breed::query()->create(['name' => 'Beagle', 'slug' => 'beagle', 'species_slug' => 'dog']);

    $this->actingAs($viewer)
        ->getJson(route('api.breeds.index', [
            'species' => 'dog',
            'q' => 'La',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Labrador Retriever')
        ->assertJsonPath('data.1.name', 'Lagotto Romagnolo')
        ->assertJsonMissing(['name' => 'LaPerm'])
        ->assertJsonMissing(['name' => 'Beagle']);
});

it('requires authentication for breed suggestions', function (): void {
    $this->getJson(route('api.breeds.index', [
        'species' => 'dog',
        'q' => 'La',
    ]))->assertUnauthorized();
});
