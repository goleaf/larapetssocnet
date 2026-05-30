<?php

use App\Enums\Pets\PetOwnerRole;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthReminder;
use App\Models\Pets\PetOwner;
use App\Models\Pets\PetRelationship;
use App\Notifications\Database\Pets\PetHealthReminderDue;
use App\Services\Pets\PetHealthReminderService;
use App\Services\Pets\PetRelationshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('creates inverse pet family relationships in the same transaction', function (): void {
    $owner = User::factory()->create();
    $parent = Pet::factory()->for($owner)->create(['name' => 'Maple', 'visibility' => 'public']);
    $offspring = Pet::factory()->for($owner)->create(['name' => 'Scout', 'visibility' => 'public']);

    $relationship = app(PetRelationshipService::class)->link(
        actor: $owner,
        source: $parent,
        target: $offspring,
        relationshipType: PetRelationship::TYPE_PARENT,
        note: 'Documented litter relationship.',
    );

    expect($relationship->relationship_type)->toBe(PetRelationship::TYPE_PARENT);

    $this->assertDatabaseHas('pet_relationships', [
        'source_pet_id' => $parent->getKey(),
        'target_pet_id' => $offspring->getKey(),
        'relationship_type' => PetRelationship::TYPE_PARENT,
        'note' => 'Documented litter relationship.',
    ]);

    $this->assertDatabaseHas('pet_relationships', [
        'source_pet_id' => $offspring->getKey(),
        'target_pet_id' => $parent->getKey(),
        'relationship_type' => PetRelationship::TYPE_OFFSPRING,
        'note' => 'Documented litter relationship.',
    ]);
});

it('does not expose private pets through family relationship linking', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $source = Pet::factory()->for($owner)->create(['visibility' => 'public']);
    $privateTarget = Pet::factory()->for($otherUser)->create([
        'visibility' => 'private',
        'is_public' => false,
    ]);

    app(PetRelationshipService::class)->link(
        actor: $owner,
        source: $source,
        target: $privateTarget,
        relationshipType: PetRelationship::TYPE_SIBLING,
    );
})->throws(ValidationException::class);

it('creates due health reminders and sends them to accepted pet co-owners', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $coOwner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['name' => 'Luna', 'visibility' => 'public']);

    PetOwner::query()->create([
        'pet_id' => $pet->getKey(),
        'user_id' => $coOwner->getKey(),
        'invited_by_user_id' => $owner->getKey(),
        'role' => PetOwnerRole::Admin->value,
        'can_manage_health' => true,
        'accepted_at' => now(),
    ]);

    $reminder = app(PetHealthReminderService::class)->create(
        actor: $owner,
        pet: $pet,
        type: PetHealthReminder::TYPE_VACCINATION,
        frequencyDays: 30,
        nextDueOn: today()->subDay(),
    );

    $this->artisan('pets:send-health-reminders')->assertSuccessful();

    Notification::assertSentTo($owner, PetHealthReminderDue::class);
    Notification::assertSentTo($coOwner, PetHealthReminderDue::class);

    $freshReminder = $reminder->fresh();

    expect($freshReminder->last_sent_on->toDateString())->toBe(today()->toDateString());
    expect($freshReminder->next_due_on->toDateString())->toBe(today()->addDays(30)->toDateString());
});

it('requires custom health reminder text', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    app(PetHealthReminderService::class)->create(
        actor: $owner,
        pet: $pet,
        type: PetHealthReminder::TYPE_CUSTOM,
        frequencyDays: 90,
        customText: null,
    );
})->throws(ValidationException::class);

it('skips archived pets when sending health reminders', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    app(PetHealthReminderService::class)->create(
        actor: $owner,
        pet: $pet,
        type: PetHealthReminder::TYPE_GROOMING,
        frequencyDays: 45,
        nextDueOn: today()->subDay(),
    );

    $pet->forceFill(['is_archived' => true])->save();

    $this->artisan('pets:send-health-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});
