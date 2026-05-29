<?php

use App\Models\Identity\SocialAccount;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('routes onboarding to a verified full-page Livewire component and redirects completed users away', function (): void {
    $route = Route::getRoutes()->getByName('onboarding.show');

    expect($route)->not->toBeNull()
        ->and($route?->gatherMiddleware())->toContain('auth.verified')
        ->and($route?->gatherMiddleware())->toContain('onboarding.incomplete')
        ->and($route?->getAction('livewire_component'))->toBe('pages.onboarding');

    $user = User::factory()->onboardingIncomplete()->create();

    $this->actingAs($user)
        ->get(route('onboarding.show'))
        ->assertOk()
        ->assertSee('data-ui="onboarding-page"', false)
        ->assertSee("Let's set up your profile.", false);

    $completed = User::factory()->create();

    $this->actingAs($completed)
        ->get(route('onboarding.show'))
        ->assertRedirect(route('feed.index'));
});

it('retains profile step data when moving backward and skips without saving profile fields', function (): void {
    $user = User::factory()->onboardingIncomplete()->create([
        'bio' => null,
        'location' => null,
    ]);

    Livewire::actingAs($user)
        ->test('pages.onboarding')
        ->set('bio', 'Loves rescue dogs and weekend walks.')
        ->set('location', 'Vilnius')
        ->call('skipProfile')
        ->assertSet('step', 2)
        ->call('back')
        ->assertSet('step', 1)
        ->assertSet('bio', 'Loves rescue dogs and weekend walks.')
        ->assertSet('location', 'Vilnius');

    expect($user->refresh()->bio)->toBeNull()
        ->and($user->location)->toBeNull();
});

it('saves optional profile details and accepts a social login avatar suggestion', function (): void {
    $user = User::factory()->onboardingIncomplete()->create([
        'bio' => null,
        'avatar_path' => null,
        'location' => null,
    ]);
    $avatarUrl = 'https://example.com/social-avatar.jpg';

    SocialAccount::factory()
        ->for($user)
        ->create(['provider_avatar_url' => $avatarUrl]);

    Livewire::actingAs($user)
        ->test('pages.onboarding')
        ->assertSet('suggestedAvatarUrl', $avatarUrl)
        ->call('acceptSuggestedAvatar')
        ->set('bio', 'I share calm city walks and cat-friendly spaces.')
        ->set('location', 'Kaunas, Lithuania')
        ->set('location_lat', '54.8985')
        ->set('location_lng', '23.9036')
        ->call('continueFromProfile')
        ->assertSet('step', 2);

    expect($user->refresh()->bio)->toBe('I share calm city walks and cat-friendly spaces.')
        ->and($user->avatar_path)->toBe($avatarUrl)
        ->and($user->location)->toBe('Kaunas, Lithuania')
        ->and((float) $user->location_lat)->toBe(54.8985);
});

it('creates the first pet inside onboarding and refreshes ten follow suggestions', function (): void {
    $user = User::factory()->onboardingIncomplete()->create(['onboarding_step' => '2']);
    createOnboardingSuggestionTargets(10, 'dog');

    $component = Livewire::actingAs($user)
        ->test('pages.onboarding')
        ->assertSet('step', 2)
        ->set('petName', 'Biscuit')
        ->set('petSpecies', 'dog')
        ->set('petBreed', 'mixed_breed')
        ->set('petBirthDate', now()->subYears(2)->toDateString())
        ->set('petGender', 'female')
        ->call('savePet')
        ->assertSet('step', 3)
        ->assertSet('createdPetSpecies', 'dog');

    $this->assertDatabaseHas('pets', [
        'user_id' => $user->getKey(),
        'name' => 'Biscuit',
        'species' => 'dog',
        'gender' => 'female',
    ]);

    expect($component->get('suggestions'))->toHaveCount(10);
});

it('persists individual follows immediately and follows all suggestions in a batch action', function (): void {
    $user = User::factory()->onboardingIncomplete()->create(['onboarding_step' => '3']);
    createOnboardingSuggestionTargets(10, 'cat');

    $component = Livewire::actingAs($user)->test('pages.onboarding');
    $suggestions = $component->get('suggestions');

    expect($suggestions)->toHaveCount(10);

    $component->call('toggleFollow', $suggestions[0]['id']);

    $this->assertDatabaseHas('follows', [
        'follower_id' => $user->getKey(),
        'following_id' => $suggestions[0]['id'],
        'status' => 'accepted',
    ]);

    $component->call('followAll');

    expect(DB::table('follows')->where('follower_id', $user->getKey())->count())->toBe(10);
});

it('completes onboarding with zero follows and schedules the skipped pet reminder once', function (): void {
    $user = User::factory()->onboardingIncomplete()->create();

    Livewire::actingAs($user)
        ->test('pages.onboarding')
        ->call('skipProfile')
        ->call('skipPet')
        ->call('completeOnboarding')
        ->assertRedirect(route('feed.index', absolute: false));

    expect($user->refresh()->onboarding_completed)->toBeTrue()
        ->and($user->onboarding_completed_at)->not->toBeNull()
        ->and($user->onboarding_pet_reminder_pending)->toBeTrue()
        ->and($user->onboarding_pet_reminder_shown_at)->toBeNull();
});

it('shows and dismisses the twenty four hour welcome banner on the feed', function (): void {
    $user = User::factory()->create([
        'name' => 'Mira Stone',
        'onboarding_completed' => true,
        'onboarding_completed_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('Welcome to PetSocial, Mira!');

    $this->post(route('onboarding.welcome-banner.dismiss'))
        ->assertRedirect();

    $this->get(route('feed.index'))
        ->assertOk()
        ->assertDontSee('Welcome to PetSocial, Mira!');
});

it('shows the skipped pet reminder only once', function (): void {
    $user = User::factory()->create([
        'onboarding_pet_reminder_pending' => true,
        'onboarding_pet_reminder_shown_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertOk()
        ->assertSee('Your profile is ready.');

    expect($user->refresh()->onboarding_pet_reminder_shown_at)->not->toBeNull();

    $this->get(route('feed.index'))
        ->assertOk()
        ->assertDontSee('Your profile is ready.');
});

function createOnboardingSuggestionTargets(int $count, string $species): void
{
    for ($index = 1; $index <= $count; $index++) {
        $target = User::factory()->create([
            'headline' => "Shares {$species} stories number {$index}.",
            'followers_count' => $count - $index,
            'show_in_explore' => true,
            'is_private' => false,
            'profile_visibility' => 'public',
        ]);

        Pet::factory()
            ->for($target, 'owner')
            ->create([
                'species' => $species,
                'visibility' => 'public',
                'is_public' => true,
            ]);
    }
}
