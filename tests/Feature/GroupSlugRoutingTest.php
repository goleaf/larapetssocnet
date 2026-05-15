<?php

use App\Models\Groups\Group;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('group show route resolves by slug and redirects from id', function (): void {
    $viewer = User::factory()->create();
    $group = Group::factory()->public()->create([
        'slug' => 'dog-walk-crew',
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertSuccessful();

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->getKey()))
        ->assertRedirect(route('groups.show', $group->slug));
});

test('group slugs are unique when names collide', function (): void {
    $owner = User::factory()->create();

    $first = Group::query()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
        'name' => 'Puppy Club',
        'slug' => null,
        'privacy' => 'public',
        'type' => 'public',
    ]);

    $second = Group::query()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
        'name' => 'Puppy Club',
        'slug' => null,
        'privacy' => 'public',
        'type' => 'public',
    ]);

    expect($first->slug)->not->toBe($second->slug);
});
