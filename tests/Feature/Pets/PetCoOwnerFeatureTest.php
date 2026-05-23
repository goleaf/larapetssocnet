<?php

use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds a co-owner with scoped permissions', function (): void {
    $owner = User::factory()->create();
    $coOwner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('pets.owners.store', $pet), [
            'user_id' => $coOwner->getKey(),
            'can_post' => '1',
            'can_edit' => '0',
            'can_manage_health' => '1',
            'can_manage_gallery' => '0',
            'can_manage_adoption' => '0',
            'can_delete' => '0',
        ])
        ->assertRedirect(route('pets.edit', $pet));

    $this->assertDatabaseHas('pet_owners', [
        'pet_id' => $pet->getKey(),
        'user_id' => $coOwner->getKey(),
        'role' => 'co_owner',
        'can_post' => 1,
        'can_manage_health' => 1,
        'can_delete' => 0,
    ]);
});

it('allows a co-owner with post permission to post for a pet without delete access', function (): void {
    $owner = User::factory()->create();
    $coOwner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $pet->ownerships()->create([
        'user_id' => $coOwner->getKey(),
        'invited_by_user_id' => $owner->getKey(),
        'role' => 'co_owner',
        'can_post' => true,
        'can_delete' => false,
        'accepted_at' => now(),
    ]);

    $this->actingAs($coOwner)
        ->post(route('posts.store'), [
            'body' => 'A co-owner field note.',
            'pet_id' => $pet->getKey(),
            'status' => PostStatus::Published->value,
            'visibility' => Post::VISIBILITY_PUBLIC,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('posts', [
        'user_id' => $coOwner->getKey(),
        'pet_id' => $pet->getKey(),
        'body' => 'A co-owner field note.',
    ]);

    $this->actingAs($coOwner)
        ->delete(route('pets.destroy', $pet))
        ->assertForbidden();
});

it('rejects pet posts from a co-owner without post permission', function (): void {
    $owner = User::factory()->create();
    $coOwner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $pet->ownerships()->create([
        'user_id' => $coOwner->getKey(),
        'invited_by_user_id' => $owner->getKey(),
        'role' => 'co_owner',
        'can_post' => false,
        'accepted_at' => now(),
    ]);

    $this->actingAs($coOwner)
        ->post(route('posts.store'), [
            'body' => 'This should not attach.',
            'pet_id' => $pet->getKey(),
            'status' => PostStatus::Published->value,
            'visibility' => Post::VISIBILITY_PUBLIC,
        ])
        ->assertSessionHasErrors(['pet_id']);
});
