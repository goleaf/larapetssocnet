<?php

use App\Models\Identity\User;
use App\Models\Pets\Breed;
use App\Models\Pets\Species;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns species-scoped breed suggestions ordered by name', function (): void {
    $viewer = User::factory()->create();
    $dog = Species::query()->create([
        'name' => 'Dog',
        'slug' => 'dog',
        'display_order' => 1,
    ]);
    $cat = Species::query()->create([
        'name' => 'Cat',
        'slug' => 'cat',
        'display_order' => 2,
    ]);

    Breed::query()->create(['name' => 'Labrador Retriever', 'slug' => 'labrador-retriever', 'species_slug' => 'dog', 'species_id' => $dog->id, 'normalized_name' => 'labradorretriever']);
    Breed::query()->create(['name' => 'Lagotto Romagnolo', 'slug' => 'lagotto-romagnolo', 'species_slug' => 'dog', 'species_id' => $dog->id, 'normalized_name' => 'lagottoromagnolo']);
    Breed::query()->create(['name' => 'LaPerm', 'slug' => 'laperm', 'species_slug' => 'cat', 'species_id' => $cat->id, 'normalized_name' => 'laperm']);
    Breed::query()->create(['name' => 'Beagle', 'slug' => 'beagle', 'species_slug' => 'dog', 'species_id' => $dog->id, 'normalized_name' => 'beagle']);

    $this->actingAs($viewer)
        ->getJson(route('api.breeds.index', [
            'species' => 'dog',
            'q' => 'La',
        ]))
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Mixed breed')
        ->assertJsonPath('data.1.name', 'Unknown breed')
        ->assertJsonPath('data.2.name', 'Labrador Retriever')
        ->assertJsonPath('data.3.name', 'Lagotto Romagnolo')
        ->assertJsonMissing(['name' => 'LaPerm'])
        ->assertJsonMissing(['name' => 'Beagle']);
});

it('requires authentication for breed suggestions', function (): void {
    $this->getJson(route('api.breeds.index', [
        'species' => 'dog',
        'q' => 'La',
    ]))->assertUnauthorized();
});
