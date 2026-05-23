<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a milestone and shares an automatic pet post when enabled', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create([
        'is_public' => true,
        'posts_count' => 0,
    ]);

    $this->actingAs($owner)
        ->post(route('pets.milestones.store', $pet), [
            'milestone_type' => PetMilestone::TYPE_LIFE_EVENT,
            'title' => 'First beach walk',
            'body' => 'Mochi learned to love waves.',
            'occurred_on' => '2026-05-20',
            'share_as_post' => '1',
        ])
        ->assertRedirect(route('pets.show', [$pet, 'tab' => 'milestones']));

    $milestone = PetMilestone::query()->where('title', 'First beach walk')->firstOrFail();

    expect($milestone->post_id)->not->toBeNull();

    $this->assertDatabaseHas('posts', [
        'id' => $milestone->post_id,
        'user_id' => $owner->getKey(),
        'pet_id' => $pet->getKey(),
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
});

it('stores a milestone without creating a post when sharing is disabled', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('pets.milestones.store', $pet), [
            'milestone_type' => PetMilestone::TYPE_TRAINING,
            'title' => 'Recall practice',
            'body' => 'Responded to every recall cue.',
            'occurred_on' => '2026-05-21',
            'share_as_post' => '0',
        ])
        ->assertRedirect(route('pets.show', [$pet, 'tab' => 'milestones']));

    $milestone = PetMilestone::query()->where('title', 'Recall practice')->firstOrFail();

    expect($milestone->post_id)->toBeNull();
    expect(Post::query()->where('pet_id', $pet->getKey())->count())->toBe(0);
});

it('allows milestone edits through the owner workflow', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create();
    $milestone = PetMilestone::factory()->for($pet)->for($owner, 'user')->create([
        'title' => 'Old title',
        'occurred_on' => '2026-05-19',
    ]);

    $this->actingAs($owner)
        ->patch(route('pets.milestones.update', [$pet, $milestone]), [
            'milestone_type' => PetMilestone::TYPE_HEALTH,
            'title' => 'Vet checkup',
            'body' => 'Clean bill of health.',
            'occurred_on' => '2026-05-22',
            'share_as_post' => '0',
        ])
        ->assertRedirect(route('pets.show', [$pet, 'tab' => 'milestones']));

    expect($milestone->fresh()->title)->toBe('Vet checkup');
});
