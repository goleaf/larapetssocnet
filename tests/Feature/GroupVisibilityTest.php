<?php

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public group is viewable', function (): void {
    $viewer = User::factory()->create();
    $group = Group::factory()->public()->create();

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertSuccessful();
});

test('secret group is hidden from non members', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $group = Group::factory()->secret()->create([
        'owner_user_id' => $owner->getKey(),
        'owner_id' => $owner->getKey(),
    ]);

    $this->actingAs($viewer)
        ->get(route('groups.show', $group->slug))
        ->assertNotFound();
});
