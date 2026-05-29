<?php

use App\Enums\Pets\PetOwnerRole;
use App\Enums\PostStatus;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetOwner;
use App\Models\Pets\PetOwnerInvitation;
use App\Models\Pets\PetOwnershipTransfer;
use App\Notifications\PetOwnerInvitationReceived;
use App\Notifications\PetOwnershipTransferRequested;
use App\Notifications\PetOwnershipTransferResolved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

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
        'role' => 'admin',
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

it('invites and accepts a pet co-owner with the poster role', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $this->actingAs($owner)
        ->post(route('pets.owner-invitations.store', $pet), [
            'user_id' => $invitee->getKey(),
            'role' => PetOwnerRole::Poster->value,
        ])
        ->assertRedirect();

    $invitation = PetOwnerInvitation::query()
        ->where('pet_id', $pet->getKey())
        ->where('invited_user_id', $invitee->getKey())
        ->firstOrFail();

    expect($invitation->status)->toBe(PetOwnerInvitation::STATUS_PENDING);
    expect($invitation->roleValue())->toBe(PetOwnerRole::Poster);
    expect($invitation->expires_at?->isSameDay(now()->addDays(14)))->toBeTrue();
    Notification::assertSentTo($invitee, PetOwnerInvitationReceived::class);

    $this->actingAs($invitee)
        ->patch(route('pets.owner-invitations.accept', [$pet, $invitation]))
        ->assertRedirect(route('pets.show', $pet));

    $this->assertDatabaseHas('pet_owner_invitations', [
        'id' => $invitation->getKey(),
        'status' => PetOwnerInvitation::STATUS_ACCEPTED,
    ]);

    $this->assertDatabaseHas('pet_owners', [
        'pet_id' => $pet->getKey(),
        'user_id' => $invitee->getKey(),
        'role' => PetOwnerRole::Poster->value,
        'can_post' => 1,
        'can_edit' => 0,
        'can_delete' => 0,
        'is_primary_owner' => 0,
    ]);
});

it('declines a pet co-owner invitation without creating ownership', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);
    $invitation = PetOwnerInvitation::query()->create([
        'pet_id' => $pet->getKey(),
        'invited_user_id' => $invitee->getKey(),
        'inviting_user_id' => $owner->getKey(),
        'role' => PetOwnerRole::Viewer->value,
        'status' => PetOwnerInvitation::STATUS_PENDING,
        'expires_at' => now()->addDays(14),
    ]);

    $this->actingAs($invitee)
        ->patch(route('pets.owner-invitations.decline', [$pet, $invitation]))
        ->assertRedirect(route('pets.index'));

    $this->assertDatabaseHas('pet_owner_invitations', [
        'id' => $invitation->getKey(),
        'status' => PetOwnerInvitation::STATUS_DECLINED,
    ]);
    $this->assertDatabaseMissing('pet_owners', [
        'pet_id' => $pet->getKey(),
        'user_id' => $invitee->getKey(),
    ]);
});

it('transfers pet ownership only after the proposed owner accepts', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $newOwner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    PetOwner::query()->create([
        'pet_id' => $pet->getKey(),
        'user_id' => $owner->getKey(),
        'role' => PetOwnerRole::Owner->value,
        'is_primary_owner' => true,
        'can_post' => true,
        'can_edit' => true,
        'can_manage_health' => true,
        'can_manage_gallery' => true,
        'can_manage_adoption' => true,
        'can_delete' => true,
        'accepted_at' => now(),
    ]);

    PetOwner::query()->create([
        'pet_id' => $pet->getKey(),
        'user_id' => $newOwner->getKey(),
        'role' => PetOwnerRole::Admin->value,
        'is_primary_owner' => false,
        'can_post' => true,
        'can_edit' => true,
        'can_manage_health' => true,
        'can_manage_gallery' => true,
        'can_manage_adoption' => true,
        'can_delete' => false,
        'accepted_at' => now(),
    ]);

    $this->actingAs($owner)
        ->post(route('pets.ownership-transfers.store', $pet), [
            'user_id' => $newOwner->getKey(),
        ])
        ->assertRedirect();

    $transfer = PetOwnershipTransfer::query()
        ->where('pet_id', $pet->getKey())
        ->where('proposed_owner_user_id', $newOwner->getKey())
        ->firstOrFail();

    expect($transfer->status)->toBe(PetOwnershipTransfer::STATUS_PENDING);
    expect($owner->can('delete', $pet->fresh()))->toBeTrue();
    Notification::assertSentTo($newOwner, PetOwnershipTransferRequested::class);

    $this->actingAs($newOwner)
        ->patch(route('pets.ownership-transfers.accept', [$pet, $transfer]))
        ->assertRedirect(route('pets.show', $pet->fresh()));

    $this->assertDatabaseHas('pets', [
        'id' => $pet->getKey(),
        'user_id' => $newOwner->getKey(),
    ]);
    $this->assertDatabaseHas('pet_owners', [
        'pet_id' => $pet->getKey(),
        'user_id' => $newOwner->getKey(),
        'role' => PetOwnerRole::Owner->value,
        'is_primary_owner' => 1,
        'can_delete' => 1,
    ]);
    $this->assertDatabaseHas('pet_owners', [
        'pet_id' => $pet->getKey(),
        'user_id' => $owner->getKey(),
        'role' => PetOwnerRole::Admin->value,
        'is_primary_owner' => 0,
        'can_delete' => 0,
    ]);
    $this->assertDatabaseHas('pet_ownership_transfers', [
        'id' => $transfer->getKey(),
        'status' => PetOwnershipTransfer::STATUS_ACCEPTED,
    ]);
    Notification::assertSentTo($owner, PetOwnershipTransferResolved::class);
});

it('deletes a declined pet ownership transfer and notifies the current owner', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $newOwner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);
    PetOwner::query()->create([
        'pet_id' => $pet->getKey(),
        'user_id' => $newOwner->getKey(),
        'role' => PetOwnerRole::Admin->value,
        'accepted_at' => now(),
    ]);
    $transfer = PetOwnershipTransfer::query()->create([
        'pet_id' => $pet->getKey(),
        'current_owner_user_id' => $owner->getKey(),
        'proposed_owner_user_id' => $newOwner->getKey(),
        'status' => PetOwnershipTransfer::STATUS_PENDING,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($newOwner)
        ->patch(route('pets.ownership-transfers.decline', [$pet, $transfer]))
        ->assertRedirect(route('pets.index'));

    $this->assertDatabaseMissing('pet_ownership_transfers', [
        'id' => $transfer->getKey(),
    ]);
    Notification::assertSentTo($owner, PetOwnershipTransferResolved::class);
});

it('expires stale pet owner invitations and ownership transfers daily', function (): void {
    $owner = User::factory()->create();
    $invitee = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $invitation = PetOwnerInvitation::query()->create([
        'pet_id' => $pet->getKey(),
        'invited_user_id' => $invitee->getKey(),
        'inviting_user_id' => $owner->getKey(),
        'role' => PetOwnerRole::Viewer->value,
        'status' => PetOwnerInvitation::STATUS_PENDING,
        'expires_at' => now()->subMinute(),
    ]);

    $transfer = PetOwnershipTransfer::query()->create([
        'pet_id' => $pet->getKey(),
        'current_owner_user_id' => $owner->getKey(),
        'proposed_owner_user_id' => $invitee->getKey(),
        'status' => PetOwnershipTransfer::STATUS_PENDING,
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('pets:expire-owner-invitations')->assertSuccessful();
    $this->artisan('pets:expire-ownership-transfers')->assertSuccessful();

    $this->assertDatabaseHas('pet_owner_invitations', [
        'id' => $invitation->getKey(),
        'status' => PetOwnerInvitation::STATUS_EXPIRED,
    ]);
    $this->assertDatabaseMissing('pet_ownership_transfers', [
        'id' => $transfer->getKey(),
    ]);
});
