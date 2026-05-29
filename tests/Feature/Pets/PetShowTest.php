<?php

use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetHealthLog;
use App\Models\Pets\PetMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('returns 200 for a public pet profile', function (): void {
    $pet = Pet::factory()
        ->for(User::factory())
        ->create([
            'name' => 'Mochi',
            'is_public' => true,
        ]);

    $this->actingAs(User::factory()->create())
        ->get(route('pets.show', $pet))
        ->assertSuccessful()
        ->assertSee('Mochi');
});

it('uses the shared full-width pet profile block system', function (): void {
    $pet = Pet::factory()
        ->for(User::factory())
        ->create([
            'name' => 'Aligned',
            'is_public' => true,
        ]);

    $this->actingAs(User::factory()->create())
        ->get(route('pets.show', $pet))
        ->assertSuccessful()
        ->assertSee('data-ui="pet-profile-stack"', false)
        ->assertSee('data-ui="pet-profile-summary"', false)
        ->assertSee('data-ui="pet-profile-tabs"', false)
        ->assertSee('data-ui="pet-profile-tab-content"', false)
        ->assertDontSee('max-w-6xl mx-auto', false);
});

it('returns 403 for a private pet profile when viewer is not authorized', function (): void {
    $pet = Pet::factory()
        ->for(User::factory())
        ->create([
            'is_public' => false,
        ]);

    $this->actingAs(User::factory()->create())
        ->get(route('pets.show', $pet))
        ->assertForbidden();
});

it('shows only the profile preview for follower-only pets before following', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()
        ->for($owner)
        ->create([
            'is_public' => true,
            'visibility' => 'followers_only',
        ]);

    Post::factory()->create([
        'user_id' => $owner->getKey(),
        'pet_id' => $pet->getKey(),
        'body' => 'Follower-only field note',
        'body_html' => '<p>Follower-only field note</p>',
    ]);

    $this->actingAs($viewer)
        ->get(route('pets.show', $pet))
        ->assertSuccessful()
        ->assertSee('data-ui="pet-profile-content-locked"', false)
        ->assertDontSeeText('Follower-only field note');
});

it('shows full follower-only pet content to pet followers', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()
        ->for($owner)
        ->create([
            'is_public' => true,
            'visibility' => 'followers_only',
        ]);

    Post::factory()->create([
        'user_id' => $owner->getKey(),
        'pet_id' => $pet->getKey(),
        'body' => 'Follower-only field note',
        'body_html' => '<p>Follower-only field note</p>',
    ]);
    $viewer->followPet($pet);

    $this->actingAs($viewer)
        ->get(route('pets.show', $pet))
        ->assertSuccessful()
        ->assertDontSee('data-ui="pet-profile-content-locked"', false)
        ->assertSeeText('Follower-only field note');
});

it('shows qr and milestone surfaces on the pet profile', function (): void {
    $owner = User::factory()->create();
    $pet = Pet::factory()
        ->for($owner)
        ->create([
            'name' => 'Timeline Buddy',
            'is_public' => true,
        ]);

    $this->actingAs($owner)
        ->get(route('pets.show', [$pet, 'tab' => 'milestones']))
        ->assertSuccessful()
        ->assertSee('data-ui="pet-profile-qr"', false)
        ->assertSee('data-ui="pet-profile-milestones"', false)
        ->assertSee(route('pets.qr.show', $pet), false);
});

it('presents pets as first-class identity profiles with life stage and story cues', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 12:00:00'));

    try {
        $owner = User::factory()->create(['name' => 'Dana Keeper']);
        $pet = Pet::factory()
            ->for($owner)
            ->create([
                'name' => 'Mochi',
                'species' => 'dog',
                'breed' => 'Beagle',
                'sex' => 'female',
                'size' => 'small',
                'birth_date' => '2020-05-23',
                'personality_tags' => ['curious', 'gentle'],
                'is_public' => true,
            ]);

        PetMilestone::factory()->for($pet)->for($owner, 'user')->create([
            'title' => 'First beach walk',
            'occurred_on' => '2026-05-20',
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('pets.show', $pet))
            ->assertSuccessful()
            ->assertSee('data-ui="pet-profile-identity-facts"', false)
            ->assertSee('data-ui="pet-life-stage"', false)
            ->assertSeeText('Adult dog')
            ->assertSeeText('Dog · Beagle')
            ->assertSeeText('Curious')
            ->assertSee('data-ui="pet-profile-identity-story"', false)
            ->assertSeeText('First beach walk')
            ->assertSeeText('Dana Keeper');
    } finally {
        Carbon::setTestNow();
    }
});

it('keeps health-derived care notes owner-only on the pet profile', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()
        ->for($owner)
        ->create([
            'name' => 'Care Buddy',
            'is_public' => true,
        ]);

    PetHealthLog::factory()->for($pet)->for($owner, 'user')->create([
        'log_type' => PetHealthLog::TYPE_WEIGHT,
        'title' => 'Morning weigh-in',
        'weight_kg' => 12.45,
        'logged_at' => now()->subDay(),
    ]);

    PetHealthLog::factory()->for($pet)->for($owner, 'user')->create([
        'log_type' => PetHealthLog::TYPE_VACCINATION,
        'title' => 'Rabies booster',
        'weight_kg' => null,
        'logged_at' => now()->subWeek(),
    ]);

    $this->actingAs($owner)
        ->get(route('pets.show', $pet))
        ->assertSuccessful()
        ->assertSee('data-ui="pet-profile-care-snapshot"', false)
        ->assertSeeText('Latest weight')
        ->assertSeeText('12.45 kg')
        ->assertSeeText('Rabies booster');

    $this->actingAs($viewer)
        ->get(route('pets.show', $pet))
        ->assertSuccessful()
        ->assertDontSee('data-ui="pet-profile-care-snapshot"', false)
        ->assertDontSeeText('12.45 kg')
        ->assertDontSeeText('Rabies booster');
});

it('shows the adopt tab for listed pets and hides it for unlisted pets', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();

    $listedPet = Pet::factory()
        ->for($owner)
        ->create([
            'name' => 'Listed Buddy',
            'is_public' => true,
            'is_adoptable' => true,
            'adoption_status' => 'available',
        ]);

    $unlistedPet = Pet::factory()
        ->for($owner)
        ->create([
            'name' => 'Home Buddy',
            'is_public' => true,
            'is_adoptable' => false,
            'adoption_status' => 'not_listed',
        ]);

    $this->actingAs($viewer)
        ->get(route('pets.show', [$listedPet, 'tab' => 'adopt']))
        ->assertSuccessful()
        ->assertSee('data-ui="pet-profile-adopt"', false);

    $this->actingAs($viewer)
        ->get(route('pets.show', [$unlistedPet, 'tab' => 'adopt']))
        ->assertSuccessful()
        ->assertDontSee('data-ui="pet-profile-adopt"', false);
});

it('returns 404 when viewer is blocked by owner', function (): void {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $pet = Pet::factory()->for($owner)->create(['is_public' => true]);

    $owner->block($viewer);

    $this->actingAs($viewer)
        ->get(route('pets.show', $pet))
        ->assertNotFound();
});
