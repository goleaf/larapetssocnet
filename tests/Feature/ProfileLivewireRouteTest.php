<?php

use App\Actions\Users\UpdateProfileAction;
use App\Exceptions\UsernameChangeCooldownException;
use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Content\PostMedia;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\CounterCacheService;
use App\Services\LocationAutocompleteService;
use App\Services\ReactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;

uses(RefreshDatabase::class);

it('routes username profiles through a full-page livewire component', function (): void {
    $route = Route::getRoutes()->getByName('profile.show');

    expect($route)->not->toBeNull()
        ->and($route->uri())->toBe('@{user}')
        ->and($route->getAction('livewire_component'))->toBe('pages.profile.show')
        ->and($route->getActionName())->toContain('LivewirePageController')
        ->and(route('profile.show', ['user' => 'social_handle'], false))->toBe('/@social_handle');
});

it('registers the public profile route after application named routes', function (): void {
    $namedRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => is_string($route->getName()) && $route->getName() !== '')
        ->values();

    $profileIndex = $namedRoutes->search(fn ($route): bool => $route->getName() === 'profile.show');

    expect($profileIndex)->not->toBeFalse();

    $applicationRoutesAfterProfile = $namedRoutes
        ->slice((int) $profileIndex + 1)
        ->reject(fn ($route): bool => str_starts_with((string) $route->getName(), 'storage.'))
        ->values();

    expect($applicationRoutesAfterProfile->map(fn ($route): ?string => $route->getName())->all())->toBe([]);
});

it('renders the public profile page from the livewire route', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Livewire Routed Member',
        'username' => 'livewire_route',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get('/@livewire_route')
        ->assertOk()
        ->assertSee('data-ui="profile-shell"', false)
        ->assertSee('Livewire Routed Member')
        ->assertSee('@livewire_route');
});

it('renders the profile page component directly', function (): void {
    if (! $this->app->providerIsLoaded(LivewireServiceProvider::class)) {
        $this->app->register(LivewireServiceProvider::class);
    }

    $profileOwner = User::factory()->create([
        'name' => 'Direct Livewire Member',
        'username' => 'direct_livewire_member',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSee('Direct Livewire Member')
        ->assertSee('@direct_livewire_member');
});

it('stops after the active user lookup when the username is missing', function (): void {
    $queries = [];

    DB::listen(function ($event) use (&$queries): void {
        $queries[] = $event->sql;
    });

    $this->get('/@missing_livewire_member')->assertNotFound();

    $profileQueries = collect($queries)
        ->filter(fn (string $query): bool => str_contains($query, 'from "users"')
            || str_contains($query, 'from "blocks"')
            || str_contains($query, 'from "follows"')
            || str_contains($query, 'from "media"'))
        ->values();

    $usernameLookups = $profileQueries
        ->filter(fn (string $query): bool => str_contains($query, 'from "users"') && str_contains($query, '"username" = ?'))
        ->values();

    expect($usernameLookups)->toHaveCount(1)
        ->and($profileQueries->implode("\n"))->not->toContain('from "blocks"')
        ->and($profileQueries->implode("\n"))->not->toContain('from "follows"')
        ->and($profileQueries->implode("\n"))->not->toContain('from "media"');
});

it('checks block relationships before private profile rendering', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'blocked_private_owner',
        'is_private' => true,
        'profile_visibility' => 'followers_only',
    ]);
    $viewer = User::factory()->create();

    $profileOwner->block($viewer);

    $this->actingAs($viewer)
        ->get('/@blocked_private_owner')
        ->assertNotFound();
});

it('renders private state from mount for non-followers', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'mount_private_owner',
        'is_private' => true,
        'profile_visibility' => 'followers_only',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('showPrivateProfile', true)
        ->assertSet('profileVisibility', 'followers_only')
        ->assertSee('This account is private')
        ->assertDontSee('data-ui="profile-shell"', false);
});

it('loads header data and defaults the active tab to posts for visible profiles', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'header_loaded_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $follower = User::factory()->create();

    $follower->follow($profileOwner);
    Pet::factory()->count(2)->for($profileOwner)->create();
    $profileOwner->forceFill(['pets_count' => 2])->saveQuietly();

    $component = Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'posts')
        ->assertSet('showPrivateProfile', false);

    $resolvedOwner = $component->instance()->profileOwner;

    expect($resolvedOwner)->toBeInstanceOf(User::class)
        ->and($resolvedOwner->relationLoaded('media'))->toBeTrue()
        ->and((int) $resolvedOwner->followers_count)->toBe(1)
        ->and((int) $resolvedOwner->pets_count)->toBe(2);
});

it('activates the pets tab through the profile livewire action', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'stats_pet_tab_owner',
        'pets_count' => 1,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Stats Tab Pet',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'posts')
        ->call('activateTab', 'pets')
        ->assertSet('activeTab', 'pets')
        ->assertSee('Stats Tab Pet')
        ->assertSee('aria-current="page"', false);

    expect(session('profiles.'.$profileOwner->getKey().'.active_tab'))->toBe('pets');
});

it('refreshes the profile pets tab count after a nested pet create event', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'pet_count_refresh_owner',
        'pets_count' => 0,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $component = Livewire::actingAs($profileOwner)
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSee('Pets (0)');

    $pet = Pet::factory()->for($profileOwner)->create([
        'name' => 'Count Refresh Pet',
    ]);
    $profileOwner->forceFill(['pets_count' => 1])->saveQuietly();

    $component
        ->dispatch('profile-pet-created', petId: $pet->getKey())
        ->assertSee('Pets (1)');
});

it('opens and closes the nested edit profile modal from the profile livewire page', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Nested Modal Owner',
        'username' => 'nested_modal_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('showEditProfileModal', false)
        ->assertSet('editProfileFocusTarget', null)
        ->call('openEditProfileModal', 'profile_modal_bio')
        ->assertSet('showEditProfileModal', true)
        ->assertSet('editProfileFocusTarget', 'profile_modal_bio')
        ->assertSee('profile-edit-modal-'.$profileOwner->getKey().'-profile_modal_bio', false)
        ->dispatch('profile-edit-closed')
        ->assertSet('showEditProfileModal', false)
        ->assertSet('editProfileFocusTarget', null);
});

it('rejects nested edit profile modal access for non owners', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'nested_modal_forbidden_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->assertForbidden();
});

it('renders the nested edit profile modal as a scrollable sectioned form', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Sectioned Modal Owner',
        'username' => 'sectioned_modal_owner',
        'privacy_display_birthdate' => false,
        'privacy_display_email' => false,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->assertSee('data-ui="profile-edit-modal-scroll"', false)
        ->assertSee('overflow-y-auto scroll-smooth', false)
        ->assertSee('data-ui="profile-edit-modal-section-basic"', false)
        ->assertSee('Basic Information')
        ->assertSee('id="profile_modal_username"', false)
        ->assertSee('id="profile_modal_display_name_counter"', false)
        ->assertSee('maxlength="50"', false)
        ->assertSee('id="profile_modal_bio_counter"', false)
        ->assertSee('maxlength="160"', false)
        ->assertSee('wire:model.live.debounce.400ms="location"', false)
        ->assertSee('aria-autocomplete="list"', false)
        ->assertSee('id="profile_modal_birth_day"', false)
        ->assertSee('id="profile_modal_birth_month"', false)
        ->assertSee('id="profile_modal_birth_year"', false)
        ->assertSee('data-ui="profile-edit-modal-section-media"', false)
        ->assertSee('Profile Media')
        ->assertSee('data-ui="profile-media-upload-grid"', false)
        ->assertSee('grid-cols-1 gap-4 md:grid-cols-2', false)
        ->assertSee('data-ui="profile-avatar-upload-panel"', false)
        ->assertSee('data-ui="profile-cover-upload-panel"', false)
        ->assertSee('data-ui="profile-avatar-drop-zone"', false)
        ->assertSee('data-ui="profile-avatar-change-photo-label"', false)
        ->assertSee('Change photo')
        ->assertSee('new FileReader()', false)
        ->assertSee('$wire.upload(\'avatar\'', false)
        ->assertSee('data-ui="profile-avatar-upload-progress"', false)
        ->assertSee('maxBytes: 3145728', false)
        ->assertSee('Max 3MB.')
        ->assertSee('data-ui="profile-cover-drop-zone"', false)
        ->assertSee('data-ui="profile-cover-change-photo-label"', false)
        ->assertSee('data-ui="profile-cover-file-reader-preview"', false)
        ->assertSee('data-ui="profile-cover-upload-progress"', false)
        ->assertSee('data-ui="profile-cover-reposition-inline"', false)
        ->assertSee('Drag the image up or down to choose the best crop.')
        ->assertSee('minWidth: 1200', false)
        ->assertSee('minHeight: 400', false)
        ->assertSee('$wire.upload(\'cover\'', false)
        ->assertSee('profile_modal_cover_position', false)
        ->assertSee('Minimum 1200x400. Max 5MB.')
        ->assertSee('id="profile_modal_avatar"', false)
        ->assertSee('id="profile_modal_cover"', false)
        ->assertSee('data-ui="profile-edit-modal-section-social"', false)
        ->assertSee('Social Links')
        ->assertSee('id="profile_modal_website"', false)
        ->assertSee('Website URL')
        ->assertSee('wire:model.live.blur="website"', false)
        ->assertSee('data-ui="profile-social-icon-website"', false)
        ->assertSee('id="profile_modal_social_x"', false)
        ->assertSee('Twitter/X username')
        ->assertSee('placeholder="@username"', false)
        ->assertSee('data-ui="profile-social-icon-x"', false)
        ->assertSee('id="profile_modal_social_instagram"', false)
        ->assertSee('Instagram username')
        ->assertSee('data-ui="profile-social-icon-instagram"', false)
        ->assertSee('id="profile_modal_social_facebook"', false)
        ->assertSee('Facebook profile URL')
        ->assertSee('data-ui="profile-social-icon-facebook"', false)
        ->assertSee('id="profile_modal_social_youtube"', false)
        ->assertSee('YouTube channel URL')
        ->assertSee('data-ui="profile-social-icon-youtube"', false)
        ->assertDontSee('profile_modal_social_tiktok', false)
        ->assertSee('data-ui="profile-edit-modal-section-privacy"', false)
        ->assertSee('Privacy')
        ->assertSee('data-ui="profile-privacy-toggle-list"', false)
        ->assertSee('data-ui="profile-privacy-toggle-account-visibility"', false)
        ->assertSee('Account Visibility')
        ->assertSee('wire:click="updateAccountVisibility(true)"', false)
        ->assertSee('data-ui="profile-privacy-toggle-age"', false)
        ->assertSee('Show age on profile')
        ->assertSee('wire:click="updateShowAge(true)"', false)
        ->assertSee('data-ui="profile-privacy-toggle-email"', false)
        ->assertSee('Allow people to find me by email address')
        ->assertSee('wire:click="updateEmailDiscovery(true)"', false)
        ->assertDontSee('profile_modal_profile_visibility', false)
        ->assertDontSee('profile_modal_privacy_display_location', false)
        ->assertDontSee('profile_modal_show_in_explore', false)
        ->assertDontSee('profile_modal_open_following', false)
        ->assertSee('novalidate', false)
        ->assertSee('scrollToTarget(targetId)', false)
        ->assertSee('scrollIntoView({ behavior: \'smooth\', block: \'center\' })', false)
        ->assertSee('@profile-edit-validation-failed.window="scrollToTarget($event.detail.target)"', false);
});

it('updates account visibility immediately from the nested privacy toggle', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Immediate Privacy Owner',
        'username' => 'immediate_privacy_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $component = Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->call('updateAccountVisibility', true)
        ->assertHasNoErrors()
        ->assertSet('account_is_private', true)
        ->assertSet('profile_visibility', 'followers_only')
        ->assertDispatched('profile-privacy-setting-saved');

    $profileOwner->refresh();

    expect($profileOwner->profile_visibility)->toBe('followers_only')
        ->and($profileOwner->is_private)->toBeTrue();

    $component
        ->call('updateAccountVisibility', false)
        ->assertHasNoErrors()
        ->assertSet('account_is_private', false)
        ->assertSet('profile_visibility', 'public')
        ->assertDispatched('profile-privacy-setting-saved');

    $profileOwner->refresh();

    expect($profileOwner->profile_visibility)->toBe('public')
        ->and($profileOwner->is_private)->toBeFalse();
});

it('updates show age immediately from the nested privacy toggle', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Age Privacy Owner',
        'username' => 'age_privacy_owner',
        'privacy_display_birthdate' => false,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->call('updateShowAge', true)
        ->assertHasNoErrors()
        ->assertSet('privacy_display_birthdate', true)
        ->assertDispatched('profile-privacy-setting-saved');

    expect($profileOwner->refresh()->privacy_display_birthdate)->toBeTrue();
});

it('updates email discovery immediately from the nested privacy toggle', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Email Discovery Owner',
        'username' => 'email_discovery_owner',
        'privacy_display_email' => false,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->call('updateEmailDiscovery', true)
        ->assertHasNoErrors()
        ->assertSet('privacy_display_email', true)
        ->assertDispatched('profile-privacy-setting-saved');

    expect($profileOwner->refresh()->privacy_display_email)->toBeTrue();
});

it('enforces the nested modal avatar upload type and size limits server side', function (Closure $avatarFactory, string $message): void {
    $profileOwner = User::factory()->create([
        'name' => 'Avatar Validation Owner',
        'username' => 'avatar_validation_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('avatar', $avatarFactory())
        ->call('save')
        ->assertHasErrors(['avatar'])
        ->assertSee($message);
})->with([
    'avatar gif type' => [fn (): UploadedFile => UploadedFile::fake()->image('avatar.gif', 300, 300), 'Avatar must be a JPG, PNG, or WEBP image.'],
    'avatar over three megabytes' => [fn (): UploadedFile => UploadedFile::fake()->image('avatar.jpg', 640, 640)->size(3073), 'Avatar must be smaller than 3MB.'],
]);

it('enforces the nested modal cover upload size and dimensions server side', function (Closure $coverFactory, string $message): void {
    $profileOwner = User::factory()->create([
        'name' => 'Cover Validation Owner',
        'username' => 'cover_validation_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('cover', $coverFactory())
        ->call('save')
        ->assertHasErrors(['cover'])
        ->assertSee($message);
})->with([
    'cover over five megabytes' => [fn (): UploadedFile => UploadedFile::fake()->image('cover.jpg', 1600, 480)->size(5121), 'Cover must be smaller than 5MB.'],
    'cover below minimum dimensions' => [fn (): UploadedFile => UploadedFile::fake()->image('cover.jpg', 1199, 400), 'Cover photo must be at least 1200 by 400 pixels.'],
]);

it('saves uploaded cover focal point from the nested edit modal', function (): void {
    Storage::fake('public');

    $profileOwner = User::factory()->create([
        'username' => 'cover_modal_position_owner',
        'cover_photo_position' => 50,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('cover', UploadedFile::fake()->image('cover.jpg', 1600, 600)->size(3000))
        ->set('cover_photo_position', 73.42)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('profile-edit-saved');

    $profileOwner->refresh();

    expect($profileOwner->getFirstMedia(User::MEDIA_COLLECTION_COVER))->not->toBeNull()
        ->and((float) $profileOwner->cover_photo_position)->toBe(73.42);
});

it('moves nested modal media uploads into permanent media storage and queues conversions', function (): void {
    Queue::fake();
    Storage::fake((string) config('media-library.disk_name'));

    $profileOwner = User::factory()->create([
        'name' => 'Queued Media Owner',
        'username' => 'queued_media_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('avatar', UploadedFile::fake()->image('avatar.jpg', 640, 640)->size(600))
        ->set('cover', UploadedFile::fake()->image('cover.jpg', 1600, 480)->size(1000))
        ->set('cover_photo_position', 64.5)
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('profile-edit-saved')
        ->assertDispatched('profile-toast', message: 'Profile updated successfully.', type: 'success');

    $profileOwner->refresh();

    expect($profileOwner->getMedia(User::MEDIA_COLLECTION_AVATAR))->toHaveCount(1)
        ->and($profileOwner->getMedia(User::MEDIA_COLLECTION_COVER))->toHaveCount(1)
        ->and((float) $profileOwner->cover_photo_position)->toBe(64.5);

    Queue::assertPushed(PerformConversionsJob::class);
});

it('registers profile media conversions at the public profile display dimensions', function (): void {
    $profileOwner = User::factory()->make();

    $profileOwner->registerMediaConversions();

    $conversions = collect($profileOwner->mediaConversions)
        ->keyBy(fn ($conversion): string => $conversion->getName());

    $avatarManipulations = $conversions
        ->get(User::MEDIA_CONVERSION_AVATAR_CARD)
        ?->getManipulations()
        ->toArray() ?? [];
    $coverManipulations = $conversions
        ->get(User::MEDIA_CONVERSION_COVER_BANNER)
        ?->getManipulations()
        ->toArray() ?? [];

    expect($avatarManipulations['fit'] ?? null)->toBe([
        Fit::Crop,
        User::PROFILE_AVATAR_CONVERSION_SIZE,
        User::PROFILE_AVATAR_CONVERSION_SIZE,
    ])->and($coverManipulations['fit'] ?? null)->toBe([
        Fit::Crop,
        User::PROFILE_COVER_CONVERSION_WIDTH,
        User::PROFILE_COVER_CONVERSION_HEIGHT,
    ]);
});

it('dispatches the first invalid field target when nested edit profile validation fails', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Invalid Modal Owner',
        'username' => 'invalid_modal_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name'])
        ->assertDispatched('profile-edit-validation-failed', target: 'profile_modal_name');
});

it('validates website URLs on blur in the nested edit profile modal', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Website Blur Owner',
        'username' => 'website_blur_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('website', 'not a url')
        ->assertHasErrors(['website' => 'url']);
});

it('normalizes social usernames on blur in the nested edit profile modal', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Social Blur Owner',
        'username' => 'social_blur_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('social_links.x', 'modalpets')
        ->assertSet('social_links.x', '@modalpets')
        ->set('social_links.instagram', 'instagram.com/modalpets')
        ->assertSet('social_links.instagram', '@modalpets');
});

it('saves profile edits from the nested modal without redirecting away from the profile page', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Original Modal Name',
        'display_name' => null,
        'username' => 'nested_modal_saver',
        'username_change_allowed_at' => null,
        'bio' => null,
        'headline' => null,
        'pronouns' => null,
        'location' => null,
        'location_lat' => null,
        'location_lng' => null,
        'website' => null,
        'social_links' => null,
        'privacy_display_location' => false,
        'privacy_display_birthdate' => false,
        'show_in_explore' => true,
        'open_following' => false,
        'birth_date' => null,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('name', 'Updated Modal Name')
        ->set('username', 'updated_nested_saver')
        ->set('display_name', 'Updated Modal Display')
        ->set('bio', 'A modal bio with enough detail to describe this profile.')
        ->set('headline', 'Nested editor')
        ->set('pronouns', 'they/them')
        ->set('location', 'Kaunas')
        ->set('location_lat', '54.8985')
        ->set('location_lng', '23.9036')
        ->set('website', 'modal.example')
        ->set('social_links.x', 'x.com/nestedmodal')
        ->set('social_links.instagram', 'instagram.com/nestedmodal')
        ->set('social_links.facebook', 'facebook.com/nestedmodal')
        ->set('social_links.youtube', 'youtube.com/@nestedmodal')
        ->set('birth_day', '1')
        ->set('birth_month', '1')
        ->set('birth_year', '1990')
        ->set('gender', 'prefer_not_to_say')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertDispatched('profile-edit-saved')
        ->assertDispatched('profile-toast', message: 'Profile updated successfully.', type: 'success')
        ->assertDispatched('profile-browser-url-replace-requested', url: '/@updated_nested_saver', username: 'updated_nested_saver');

    $profileOwner->refresh();

    expect($profileOwner->name)->toBe('Updated Modal Name')
        ->and($profileOwner->username)->toBe('updated_nested_saver')
        ->and($profileOwner->username_change_allowed_at)->not->toBeNull()
        ->and($profileOwner->display_name)->toBe('Updated Modal Display')
        ->and($profileOwner->bio)->toBe('A modal bio with enough detail to describe this profile.')
        ->and($profileOwner->headline)->toBe('Nested editor')
        ->and($profileOwner->pronouns)->toBe('they/them')
        ->and($profileOwner->location)->toBe('Kaunas')
        ->and($profileOwner->location_lat)->toBe(54.8985)
        ->and($profileOwner->location_lng)->toBe(23.9036)
        ->and($profileOwner->city)->toBe('Kaunas')
        ->and($profileOwner->website)->toBe('https://modal.example')
        ->and($profileOwner->birth_date?->toDateString())->toBe('1990-01-01')
        ->and($profileOwner->gender)->toBe('prefer_not_to_say')
        ->and($profileOwner->social_links)->toMatchArray([
            'x' => 'https://x.com/nestedmodal',
            'instagram' => 'https://instagram.com/nestedmodal',
            'facebook' => 'https://facebook.com/nestedmodal',
            'youtube' => 'https://youtube.com/@nestedmodal',
        ])
        ->and($profileOwner->profile_visibility)->toBe('public')
        ->and($profileOwner->is_private)->toBeFalse()
        ->and($profileOwner->privacy_display_location)->toBeFalse()
        ->and($profileOwner->privacy_display_birthdate)->toBeFalse()
        ->and($profileOwner->show_in_explore)->toBeTrue()
        ->and($profileOwner->open_following)->toBeFalse();
});

it('checks username availability and reports the cooldown state in the edit profile modal', function (): void {
    User::factory()->create([
        'username' => 'taken_modal_name',
    ]);

    $profileOwner = User::factory()->create([
        'name' => 'Cooldown Modal Owner',
        'username' => 'cooldown_modal_owner',
        'username_change_allowed_at' => now()->subDays(5),
        'gender' => 'prefer_not_to_say',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->assertSet('usernameStatus', 'ok')
        ->assertSee('You can only change your username once every 30 days. Your next change is available in', false)
        ->set('username', 'taken_modal_name')
        ->assertSet('usernameStatus', 'locked')
        ->call('save')
        ->assertHasErrors(['username'])
        ->assertDispatched('profile-edit-validation-failed', target: 'profile_modal_username');

    expect($profileOwner->fresh()->username)->toBe('cooldown_modal_owner');
});

it('checks username availability for available and taken names when cooldown is clear', function (): void {
    User::factory()->create([
        'username' => 'already_used_modal',
    ]);

    $profileOwner = User::factory()->create([
        'username' => 'available_modal_owner',
        'username_change_allowed_at' => null,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('username', 'fresh_modal_name')
        ->assertSet('usernameStatus', 'ok')
        ->assertSet('usernameMessage', 'Username is available!')
        ->set('username', 'already_used_modal')
        ->assertSet('usernameStatus', 'taken');
});

it('loads location suggestions server side and stores selected coordinates', function (): void {
    $this->instance(LocationAutocompleteService::class, new class extends LocationAutocompleteService
    {
        public function suggest(string $query, int $limit = 5): array
        {
            expect($query)->toBe('Viln')
                ->and($limit)->toBe((int) config('services.geocoding.limit', 5));

            return [[
                'label' => 'Vilnius, Lithuania',
                'latitude' => 54.6872,
                'longitude' => 25.2797,
            ]];
        }
    });

    $profileOwner = User::factory()->create([
        'username' => 'location_modal_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('location', 'Viln')
        ->assertSet('locationSuggestionsOpen', true)
        ->assertSee('Vilnius, Lithuania')
        ->call('selectLocationSuggestion', 0)
        ->assertSet('location', 'Vilnius, Lithuania')
        ->assertSet('location_lat', '54.6872')
        ->assertSet('location_lng', '25.2797')
        ->assertSet('locationSuggestionsOpen', false);
});

it('validates basic information length limits in the nested edit profile modal', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'length_modal_owner',
        'gender' => 'prefer_not_to_say',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.edit-modal', ['userId' => $profileOwner->getKey()])
        ->set('display_name', str_repeat('a', 51))
        ->set('bio', str_repeat('b', 161))
        ->call('save')
        ->assertHasErrors([
            'display_name',
            'bio',
        ]);
});

it('enforces username cooldown inside the profile update action', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Action Cooldown Owner',
        'username' => 'action_cooldown_owner',
        'username_change_allowed_at' => now()->subDays(5),
    ]);

    expect(fn () => app(UpdateProfileAction::class)->handle($profileOwner, [
        'name' => 'Action Cooldown Owner',
        'username' => 'action_blocked_name',
    ]))->toThrow(UsernameChangeCooldownException::class, 'You can only change your username once every 30 days.');

    expect($profileOwner->fresh()->username)->toBe('action_cooldown_owner');
});

it('restores the last profile tab from the browser session', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'session_tab_owner',
        'pets_count' => 1,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Remembered Session Pet',
    ]);

    $this->withSession([
        'profiles.'.$profileOwner->getKey().'.active_tab' => 'pets',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'pets')
        ->assertSee('Remembered Session Pet')
        ->assertSee('href="#pets"', false);
});

it('ignores an owner-only scheduled tab stored for a visitor session', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'visitor_scheduled_session_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->actingAs(User::factory()->create())
        ->withSession([
            'profiles.'.$profileOwner->getKey().'.active_tab' => 'scheduled',
        ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'posts')
        ->assertDontSee('Scheduled (', false);
});

it('renders hash based profile tab links for browser activation', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'hash_tabs_owner',
        'pets_count' => 1,
        'photos_count' => 2,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $this->get('/@hash_tabs_owner')
        ->assertOk()
        ->assertSee('x-data="profileTabs(', false)
        ->assertSee('href="#posts"', false)
        ->assertSee('href="#pets"', false)
        ->assertSee('href="#photos"', false)
        ->assertSee('href="#about"', false)
        ->assertDontSee('?tab=pets', false);
});

it('mounts the lazy pets tab component and fetches pets independently', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'child_pet_tab_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Nested Component Pet',
    ]);

    Livewire::test('profile.tabs.pets', ['profileUserId' => $profileOwner->getKey()])
        ->assertSee('Nested Component Pet')
        ->assertSee('data-ui="profile-tab-panel"', false);
});

it('mounts the lazy photos tab component and fetches visible post photos independently', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'child_photo_tab_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $post = Post::factory()->for($profileOwner)->create([
        'body' => 'Nested photo tab post',
        'body_html' => '<p>Nested photo tab post</p>',
        'type' => Post::TYPE_PHOTO,
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);
    PostMedia::factory()->for($post, 'post')->create([
        'file_path' => 'posts/nested-photo-tab.jpg',
        'media_type' => 'image',
    ]);

    Livewire::test('profile.tabs.photos', ['profileUserId' => $profileOwner->getKey()])
        ->assertSee('nested-photo-tab.jpg')
        ->assertSee('data-ui="profile-photos-grid"', false);
});

it('mounts the lazy about tab component and presents public biographical sections', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 12:00:00'));

    try {
        $profileOwner = User::factory()->create([
            'name' => 'Biographical Owner',
            'display_name' => 'Bio Crew',
            'username' => 'bio_section_owner',
            'email' => 'bio-owner@example.test',
            'headline' => 'Neighborhood rescue coordinator',
            'pronouns' => 'they/them',
            'bio' => "I help senior pets find patient homes.\nEvery weekend, I coordinate foster visits and transport.",
            'location' => 'Vilnius',
            'website' => 'https://bio.example/about',
            'social_links' => [
                'instagram' => 'https://instagram.com/bio_pets',
            ],
            'interests_text' => 'rescue, senior pets, training',
            'birth_date' => '1998-05-20',
            'last_active_at' => Carbon::parse('2026-05-22 12:00:00'),
            'created_at' => Carbon::parse('2024-04-14 12:00:00'),
            'posts_count' => 12,
            'pets_count' => 3,
            'photos_count' => 8,
            'post_reactions_received_count' => 34,
            'post_comments_received_count' => 9,
            'last_post_created_at' => Carbon::parse('2026-05-21 10:00:00'),
            'privacy_display_email' => true,
            'privacy_display_location' => true,
            'privacy_display_birthdate' => true,
            'privacy_display_last_seen' => true,
            'is_private' => false,
            'profile_visibility' => 'public',
        ]);

        Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('data-ui="profile-tab-panel"', false)
            ->assertSee('data-ui="profile-about-bio"', false)
            ->assertSee('I help senior pets find patient homes.')
            ->assertSee('Every weekend, I coordinate foster visits and transport.')
            ->assertDontSee('line-clamp', false)
            ->assertSee('data-ui="profile-about-bio-details"', false)
            ->assertSee('Member since April 2024.')
            ->assertSee('data-icon="calendar"', false)
            ->assertSee('data-icon="map-pin"', false)
            ->assertSee('Vilnius')
            ->assertSee('data-icon="external-link"', false)
            ->assertSee('href="https://bio.example/about"', false)
            ->assertSee('bio.example/about')
            ->assertSee('data-icon="cake"', false)
            ->assertSee('Age 28')
            ->assertDontSee('May 20, 1998')
            ->assertSee('data-ui="profile-about-activity-summary"', false)
            ->assertSee('grid grid-cols-2 gap-3', false)
            ->assertSee('Activity summary')
            ->assertSee('Posts created')
            ->assertSee('12')
            ->assertSee('Reactions received')
            ->assertSee('34')
            ->assertSee('Comments received')
            ->assertSee('9')
            ->assertSee('Most recent post')
            ->assertSee('May 21, 2026')
            ->assertDontSee('data-ui="profile-about-activity-chart"', false)
            ->assertSee('data-ui="profile-about-overview"', false)
            ->assertSee('Profile basics')
            ->assertSee('About Bio Crew')
            ->assertSee('Neighborhood rescue coordinator')
            ->assertSee('they/them')
            ->assertSee('data-ui="profile-about-interests"', false)
            ->assertSee('rescue')
            ->assertSee('senior pets')
            ->assertSee('training')
            ->assertSee('data-ui="profile-about-contact"', false)
            ->assertSee('Instagram')
            ->assertSee('href="https://instagram.com/bio_pets"', false)
            ->assertSee('@bio_pets')
            ->assertDontSee('mailto:bio-owner@example.test', false);
    } finally {
        Carbon::setTestNow();
    }
});

it('renders the about activity summary from precomputed user columns without live aggregate queries', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 12:00:00'));

    try {
        $profileOwner = User::factory()->create([
            'name' => 'Activity Owner',
            'username' => 'activity_summary_owner',
            'bio' => 'Activity summary reads cached values.',
            'is_private' => false,
            'profile_visibility' => 'public',
        ]);
        $reactor = User::factory()->create();
        $commenter = User::factory()->create();

        $post = Post::factory()->for($profileOwner)->create([
            'created_at' => Carbon::parse('2026-05-20 09:00:00'),
            'updated_at' => Carbon::parse('2026-05-20 09:00:00'),
            'visibility' => Post::VISIBILITY_PUBLIC,
        ]);

        Comment::factory()
            ->for($post)
            ->for($commenter, 'user')
            ->create();

        app(ReactionService::class)->react($reactor, $post->fresh(), 'love');

        $profileOwner->refresh();

        expect((int) $profileOwner->posts_count)->toBe(1)
            ->and((int) $profileOwner->post_comments_received_count)->toBe(1)
            ->and((int) $profileOwner->post_reactions_received_count)->toBe(1)
            ->and($profileOwner->last_post_created_at?->toDateTimeString())->toBe('2026-05-20 09:00:00');

        $profileOwner->updateQuietly([
            'posts_count' => 0,
            'post_comments_received_count' => 0,
            'post_reactions_received_count' => 0,
            'last_post_created_at' => null,
        ]);

        app(CounterCacheService::class)->rebuildProfileActivitySummary();
        $profileOwner->refresh();

        expect((int) $profileOwner->posts_count)->toBe(1)
            ->and((int) $profileOwner->post_comments_received_count)->toBe(1)
            ->and((int) $profileOwner->post_reactions_received_count)->toBe(1)
            ->and($profileOwner->last_post_created_at?->toDateTimeString())->toBe('2026-05-20 09:00:00');

        DB::enableQueryLog();

        Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('data-ui="profile-about-activity-summary"', false)
            ->assertSee('Posts created')
            ->assertSee('1')
            ->assertSee('Reactions received')
            ->assertSee('Comments received')
            ->assertSee('May 20, 2026');

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn (string $query): string => strtolower($query))
            ->implode("\n");

        DB::disableQueryLog();

        expect($queries)
            ->not->toContain('from "posts"')
            ->not->toContain('count(*)')
            ->not->toContain('sum(')
            ->not->toContain('max(');
    } finally {
        DB::disableQueryLog();
        Carbon::setTestNow();
    }
});

it('renders a compact about pet summary from visible pets without per-pet queries', function (): void {
    Storage::fake('public');

    $profileOwner = User::factory()->create([
        'name' => 'Pet Summary Owner',
        'display_name' => 'Pet Summary Crew',
        'username' => 'pet_summary_owner',
        'bio' => 'Pet summary should preview visible pets.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $visiblePet = Pet::factory()->for($profileOwner)->create([
        'name' => 'Pickles',
        'is_public' => true,
        'created_at' => Carbon::parse('2026-05-22 10:00:00'),
    ]);
    $olderVisiblePet = Pet::factory()->for($profileOwner)->create([
        'name' => 'Mochi',
        'is_public' => true,
        'created_at' => Carbon::parse('2026-05-20 10:00:00'),
    ]);
    $privatePet = Pet::factory()->for($profileOwner)->create([
        'name' => 'Hidden Whiskers',
        'is_public' => false,
        'created_at' => Carbon::parse('2026-05-23 10:00:00'),
    ]);

    $visiblePet->addMedia(UploadedFile::fake()->image('pickles.jpg', 160, 160))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);
    $olderVisiblePet->addMedia(UploadedFile::fake()->image('mochi.jpg', 160, 160))
        ->toMediaCollection(Pet::MEDIA_COLLECTION_AVATAR);

    DB::enableQueryLog();

    Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertSee('data-ui="profile-about-activity-summary"', false)
        ->assertSee('data-ui="profile-about-pet-summary"', false)
        ->assertSee('Pets at a glance')
        ->assertSee('href="'.route('pets.show', ['pet' => $visiblePet->slug]).'"', false)
        ->assertSee('href="'.route('pets.show', ['pet' => $olderVisiblePet->slug]).'"', false)
        ->assertSee('Pickles')
        ->assertSee('Pickles profile photo')
        ->assertSee('Mochi')
        ->assertSee('Mochi profile photo')
        ->assertDontSee('Hidden Whiskers');

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->map(fn (string $query): string => strtolower($query));

    DB::disableQueryLog();

    expect($queries->filter(fn (string $query): bool => str_contains($query, 'from "pets"'))->count())->toBe(1)
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "media"'))->count())->toBe(1)
        ->and($queries->implode("\n"))
        ->not->toContain('from "species"')
        ->not->toContain('from "breeds"')
        ->not->toContain('from "pet_tags"');
});

it('shows private pets in the about pet summary to the profile owner', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Private Pet Owner',
        'username' => 'private_pet_summary_owner',
        'bio' => 'Owner can see all pets in their summary.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Private Pancake',
        'is_public' => false,
    ]);

    Livewire::actingAs($profileOwner)
        ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertSee('data-ui="profile-about-pet-summary"', false)
        ->assertSee('Private Pancake');
});

it('renders mutual connections from a database intersection for authenticated visitors', function (): void {
    Storage::fake('public');

    $viewer = User::factory()->create([
        'name' => 'Mutual Viewer',
        'username' => 'mutual_about_viewer',
    ]);
    $profileOwner = User::factory()->create([
        'name' => 'Mutual Profile Owner',
        'username' => 'mutual_about_owner',
        'bio' => 'Mutual connections should be shown to visitors.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    $mutualUsers = collect(range(1, 10))->map(function (int $index) use ($viewer, $profileOwner): User {
        $mutual = User::factory()->create([
            'name' => 'Shared Friend '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'username' => 'shared_friend_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]);

        $viewer->follow($mutual);
        $mutual->follow($profileOwner);

        return $mutual;
    });

    $mutualUsers->take(2)->each(function (User $mutual, int $index): void {
        $mutual->addMedia(UploadedFile::fake()->image('shared-'.$index.'.jpg', 160, 160))
            ->toMediaCollection(User::MEDIA_COLLECTION_AVATAR);
    });

    $viewerOnly = User::factory()->create([
        'name' => 'Viewer Only Follow',
        'username' => 'viewer_only_follow',
    ]);
    $profileOnly = User::factory()->create([
        'name' => 'Profile Only Follower',
        'username' => 'profile_only_follower',
    ]);

    $viewer->follow($viewerOnly);
    $viewer->follow($profileOwner);
    $profileOnly->follow($profileOwner);

    DB::enableQueryLog();

    try {
        Livewire::actingAs($viewer)
            ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('data-ui="profile-about-mutual-connections"', false)
            ->assertSee('Mutual connections')
            ->assertSee('Shared Friend 01')
            ->assertSee('Shared Friend 01 profile photo')
            ->assertSee('href="'.route('profile.show', ['user' => 'shared_friend_01']).'"', false)
            ->assertDontSee('Viewer Only Follow')
            ->assertDontSee('Profile Only Follower')
            ->assertSee('See all 10 mutual connections')
            ->assertSee('href="'.route('profile.followers', ['user' => $profileOwner->username, 'mutual' => 1]).'"', false);

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn (string $query): string => strtolower($query));
    } finally {
        DB::disableQueryLog();
    }

    $mutualQueries = $queries->filter(
        fn (string $query): bool => str_contains($query, 'join "follows" as "viewer_following"')
            && str_contains($query, 'join "follows" as "profile_followers"')
    )->values();

    expect($mutualQueries)->toHaveCount(2)
        ->and($mutualQueries->implode("\n"))
        ->toContain('"viewer_following"."following_id" = "users"."id"')
        ->toContain('"profile_followers"."follower_id" = "users"."id"')
        ->toContain('count("users"."id") as aggregate')
        ->toContain('limit 8')
        ->not->toContain('select *')
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "media"'))->count())->toBe(1);
});

it('renders also followed by recommendations for authenticated non-followers', function (): void {
    Storage::fake('public');

    $viewer = User::factory()->create([
        'name' => 'Recommendation Viewer',
        'username' => 'recommendation_viewer',
    ]);
    $profileOwner = User::factory()->create([
        'name' => 'Recommendation Owner',
        'username' => 'recommendation_owner',
        'bio' => 'Recommendation social proof should help visitors decide to follow.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    collect(range(1, 5))->each(function (int $index) use ($viewer, $profileOwner): void {
        $connection = User::factory()->create([
            'name' => 'Recommendation Friend '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'username' => 'recommendation_friend_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]);

        $viewer->follow($connection);
        $connection->follow($profileOwner);
    });

    $viewerOnly = User::factory()->create([
        'name' => 'Recommendation Viewer Only',
        'username' => 'recommendation_viewer_only',
    ]);
    $profileOnly = User::factory()->create([
        'name' => 'Recommendation Profile Only',
        'username' => 'recommendation_profile_only',
    ]);

    $viewer->follow($viewerOnly);
    $profileOnly->follow($profileOwner);

    DB::enableQueryLog();

    try {
        Livewire::actingAs($viewer)
            ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('data-ui="profile-about-also-followed-by"', false)
            ->assertSee('Also followed by')
            ->assertSee('People you follow already follow Recommendation Owner.')
            ->assertSeeInOrder([
                'data-ui="profile-about-also-followed-by"',
                'Recommendation Friend 01',
                'Recommendation Friend 05',
            ], false)
            ->assertDontSee('Recommendation Viewer Only')
            ->assertDontSee('Recommendation Profile Only');

        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->map(fn (string $query): string => strtolower($query));
    } finally {
        DB::disableQueryLog();
    }

    $recommendationQueries = $queries->filter(
        fn (string $query): bool => str_contains($query, 'join "follows" as "viewer_following"')
            && str_contains($query, 'join "follows" as "profile_followers"')
            && str_contains($query, 'limit 5')
    )->values();

    expect($recommendationQueries)->toHaveCount(1)
        ->and($recommendationQueries->first())
        ->toContain('"viewer_following"."following_id" = "users"."id"')
        ->toContain('"profile_followers"."follower_id" = "users"."id"')
        ->not->toContain('select *')
        ->and($queries->filter(fn (string $query): bool => str_contains($query, 'from "media"'))->count())->toBe(2);
});

it('hides also followed by recommendations from guests owners followers and sparse matches', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'Recommendation Hidden Owner',
        'username' => 'recommendation_hidden_owner',
        'bio' => 'Recommendation social proof should stay scoped.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $viewer = User::factory()->create([
        'name' => 'Recommendation Hidden Viewer',
        'username' => 'recommendation_hidden_viewer',
    ]);

    collect(range(1, 3))->each(function (int $index) use ($viewer, $profileOwner): void {
        $connection = User::factory()->create([
            'name' => 'Hidden Recommendation Friend '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'username' => 'hidden_recommendation_friend_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]);

        $viewer->follow($connection);
        $connection->follow($profileOwner);
    });

    Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertDontSee('data-ui="profile-about-also-followed-by"', false)
        ->assertDontSee('Also followed by');

    Livewire::actingAs($profileOwner)
        ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertDontSee('data-ui="profile-about-also-followed-by"', false)
        ->assertDontSee('Also followed by');

    $viewer->follow($profileOwner);

    Livewire::actingAs($viewer)
        ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertDontSee('data-ui="profile-about-also-followed-by"', false)
        ->assertDontSee('Also followed by');

    $sparseViewer = User::factory()->create([
        'name' => 'Sparse Recommendation Viewer',
        'username' => 'sparse_recommendation_viewer',
    ]);
    $sparseOwner = User::factory()->create([
        'name' => 'Sparse Recommendation Owner',
        'username' => 'sparse_recommendation_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    collect(range(1, 2))->each(function (int $index) use ($sparseViewer, $sparseOwner): void {
        $connection = User::factory()->create([
            'name' => 'Sparse Recommendation Friend '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            'username' => 'sparse_recommendation_friend_'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        ]);

        $sparseViewer->follow($connection);
        $connection->follow($sparseOwner);
    });

    Livewire::actingAs($sparseViewer)
        ->test('profile.tabs.about', ['profileUserId' => $sparseOwner->getKey()])
        ->assertDontSee('data-ui="profile-about-also-followed-by"', false)
        ->assertDontSee('Also followed by');
});

it('hides about mutual connections from guests and profile owners', function (): void {
    $profileOwner = User::factory()->create([
        'name' => 'No Mutual Owner',
        'username' => 'no_mutual_owner',
        'bio' => 'This visitor-only panel should stay hidden here.',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertDontSee('data-ui="profile-about-mutual-connections"', false)
        ->assertDontSee('Mutual connections');

    Livewire::actingAs($profileOwner)
        ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
        ->assertDontSee('data-ui="profile-about-mutual-connections"', false)
        ->assertDontSee('Mutual connections');
});

it('filters the followers page to mutual connections when requested from the about link', function (): void {
    $viewer = User::factory()->create([
        'name' => 'Filtered Mutual Viewer',
        'username' => 'filtered_mutual_viewer',
    ]);
    $profileOwner = User::factory()->create([
        'name' => 'Filtered Mutual Owner',
        'username' => 'filtered_mutual_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);
    $mutual = User::factory()->create([
        'name' => 'Filtered Shared Friend',
        'username' => 'filtered_shared_friend',
    ]);
    $profileOnly = User::factory()->create([
        'name' => 'Filtered Profile Only',
        'username' => 'filtered_profile_only',
    ]);
    $viewerOnly = User::factory()->create([
        'name' => 'Filtered Viewer Only',
        'username' => 'filtered_viewer_only',
    ]);

    $viewer->follow($mutual);
    $mutual->follow($profileOwner);
    $profileOnly->follow($profileOwner);
    $viewer->follow($viewerOnly);

    $this->actingAs($viewer)
        ->get(route('profile.followers', ['user' => $profileOwner->username, 'mutual' => 1]))
        ->assertOk()
        ->assertSee('Mutual connections')
        ->assertSee('Filtered Shared Friend')
        ->assertDontSee('Filtered Profile Only')
        ->assertDontSee('Filtered Viewer Only')
        ->assertSee('name="mutual"', false);
});

it('keeps privacy-gated about fields hidden from visitors and never reveals the birth date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-23 12:00:00'));

    try {
        $profileOwner = User::factory()->create([
            'name' => 'Private Details Owner',
            'username' => 'private_about_fields',
            'email' => 'private-about@example.test',
            'bio' => 'Public bio remains visible.',
            'location' => 'Hidden City',
            'website' => null,
            'social_links' => null,
            'interests_text' => null,
            'birth_date' => '1994-05-20',
            'last_active_at' => Carbon::parse('2026-05-22 12:00:00'),
            'privacy_display_email' => false,
            'privacy_display_location' => false,
            'privacy_display_birthdate' => false,
            'privacy_display_last_seen' => false,
            'is_private' => false,
            'profile_visibility' => 'public',
        ]);

        Livewire::test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('Public bio remains visible.')
            ->assertSee('Member since')
            ->assertDontSee('Hidden City')
            ->assertDontSee('May 20, 1994')
            ->assertDontSee('Age 32')
            ->assertDontSee('private-about@example.test')
            ->assertDontSee('mailto:private-about@example.test', false);

        Livewire::actingAs($profileOwner)
            ->test('profile.tabs.about', ['profileUserId' => $profileOwner->getKey()])
            ->assertSee('Hidden City')
            ->assertDontSee('May 20, 1994')
            ->assertDontSee('Age 32')
            ->assertDontSee('private-about@example.test')
            ->assertDontSee('mailto:private-about@example.test', false);
    } finally {
        Carbon::setTestNow();
    }
});

it('mounts the nested posts tab only after posts becomes the active profile tab', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'child_post_tab_owner',
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Pet::factory()->for($profileOwner)->create([
        'name' => 'Initially Active Pet Tab',
    ]);

    Post::factory()->for($profileOwner)->create([
        'body' => 'Nested Component Post Body',
        'body_html' => '<p>Nested Component Post Body</p>',
        'visibility' => Post::VISIBILITY_PUBLIC,
    ]);

    $this->withSession([
        'profiles.'.$profileOwner->getKey().'.active_tab' => 'pets',
    ]);

    Livewire::test('pages.profile.show', ['user' => $profileOwner->username])
        ->assertSet('activeTab', 'pets')
        ->assertSee('Initially Active Pet Tab')
        ->assertDontSee('Nested Component Post Body')
        ->assertDontSee('id="profile-panel-posts"', false)
        ->call('activateTab', 'posts')
        ->assertSet('activeTab', 'posts')
        ->assertSee('Nested Component Post Body')
        ->assertSee('id="profile-panel-posts"', false);
});

it('saves cover focal point through the profile livewire action for the owner', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'cover_action_owner',
        'cover_photo_position' => 50,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->call('saveCoverPosition', 78.456)
        ->assertReturned(78.46);

    expect((float) $profileOwner->refresh()->cover_photo_position)->toBe(78.46);
});

it('rejects cover focal point livewire saves from non-owners', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'cover_action_locked',
        'cover_photo_position' => 50,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs(User::factory()->create())
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->call('saveCoverPosition', 80)
        ->assertForbidden();

    expect((float) $profileOwner->refresh()->cover_photo_position)->toBe(50.0);
});

it('validates cover focal point values in the profile livewire action', function (): void {
    $profileOwner = User::factory()->create([
        'username' => 'cover_action_validation',
        'cover_photo_position' => 50,
        'is_private' => false,
        'profile_visibility' => 'public',
    ]);

    Livewire::actingAs($profileOwner)
        ->test('pages.profile.show', ['user' => $profileOwner->username])
        ->call('saveCoverPosition', 101)
        ->assertHasErrors(['position' => 'max']);

    expect((float) $profileOwner->refresh()->cover_photo_position)->toBe(50.0);
});
