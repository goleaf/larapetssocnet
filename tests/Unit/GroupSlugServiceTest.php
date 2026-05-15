<?php

use App\Models\Groups\Group;
use App\Services\GroupSlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('slug service normalizes and reserves slugs', function (): void {
    $service = app(GroupSlugService::class);

    expect($service->normalize('  Puppy Club  '))->toBe('puppy-club');
    expect($service->generateUnique('create'))->toBe('create-group');
});

test('slug service generates unique slugs when collisions exist', function (): void {
    Group::factory()->create([
        'slug' => 'puppy-club',
    ]);

    $service = app(GroupSlugService::class);

    expect($service->generateUnique('puppy club'))->toBe('puppy-club-1');
});
