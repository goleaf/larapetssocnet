<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the settings workspace and profile form with submit controls', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.profile'))
        ->assertOk()
        ->assertSee('data-ui="settings-shell"', false)
        ->assertSee('data-ui="settings-header"', false)
        ->assertSee('data-ui="sidebar-nav"', false)
        ->assertSee('data-ui="settings-profile-form"', false)
        ->assertSee('data-ui="profile-avatar-section"', false)
        ->assertSee('data-ui="profile-cover-section"', false)
        ->assertSee('data-ui="profile-theme-section"', false)
        ->assertSee('name="profile_theme"', false)
        ->assertSee('type="submit"', false)
        ->assertSee('min-h-11', false)
        ->assertSee('focus-visible:outline-paw', false);
});

it('renders privacy and notification toggles as accessible switches', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.privacy'))
        ->assertOk()
        ->assertSee('data-ui="settings-privacy-form"', false)
        ->assertSee('data-ui="settings-toggle"', false)
        ->assertSee('role="switch"', false)
        ->assertSee('aria-labelledby="toggle-show-in-explore-label"', false)
        ->assertSee('type="submit"', false);

    $this->actingAs($user)
        ->get(route('settings.notifications'))
        ->assertOk()
        ->assertSee('data-ui="settings-notifications-form"', false)
        ->assertSee('aria-labelledby="toggle-notifications-post-likes-label"', false)
        ->assertSee('data-ui="settings-toggle"', false)
        ->assertSee('type="submit"', false);
});

it('renders security media and data settings with actionable forms', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.password'))
        ->assertOk()
        ->assertSee('data-ui="settings-password-form"', false)
        ->assertSee('type="submit"', false)
        ->assertSee('Save Password');

    $this->actingAs($user)
        ->get(route('settings.photos'))
        ->assertOk()
        ->assertSee('data-ui="settings-photos-page"', false)
        ->assertSee('type="submit"', false)
        ->assertSee('Create Gallery');

    $this->actingAs($user)
        ->get(route('settings.data'))
        ->assertOk()
        ->assertSee('data-ui="settings-data-page"', false)
        ->assertSee('Download Archive (JSON)')
        ->assertSee('min-h-11', false);

    $this->actingAs($user)
        ->get(route('settings.blocked'))
        ->assertOk()
        ->assertSee('data-ui="settings-blocked-page"', false)
        ->assertSee('Block User')
        ->assertSee('min-h-11', false);
});
