<?php

use App\Models\Identity\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('skips privacy updates when the settings payload is empty', function (): void {
    $user = User::factory()->create([
        'profile_visibility' => 'public',
        'messaging_permission' => 'everyone',
        'pets_visibility' => 'everyone',
        'groups_visibility' => 'everyone',
        'show_in_explore' => true,
        'open_following' => false,
        'privacy_display_last_seen' => true,
    ]);

    app(SettingsService::class)->savePrivacySettings($user, []);

    $user->refresh();

    expect($user->profile_visibility)->toBe('public');
    expect($user->messaging_permission)->toBe('everyone');
    expect($user->pets_visibility)->toBe('everyone');
    expect($user->groups_visibility)->toBe('everyone');
    expect($user->show_in_explore)->toBeTrue();
    expect($user->open_following)->toBeFalse();
    expect($user->privacy_display_last_seen)->toBeTrue();
});

it('updates only whitelisted privacy settings and normalizes boolean flags', function (): void {
    $user = User::factory()->create([
        'profile_visibility' => 'public',
        'messaging_permission' => 'everyone',
        'pets_visibility' => 'everyone',
        'groups_visibility' => 'everyone',
        'show_in_explore' => true,
        'open_following' => false,
        'privacy_display_last_seen' => true,
        'name' => 'Original Name',
    ]);

    app(SettingsService::class)->savePrivacySettings($user, [
        'profile_visibility' => 'followers_only',
        'messaging_permission' => 'followers_only',
        'show_in_explore' => 0,
        'open_following' => '1',
        'privacy_display_last_seen' => '',
        'name' => 'Should Stay Original',
    ]);

    $user->refresh();

    expect($user->profile_visibility)->toBe('followers_only');
    expect($user->messaging_permission)->toBe('followers_only');
    expect($user->pets_visibility)->toBe('everyone');
    expect($user->groups_visibility)->toBe('everyone');
    expect($user->show_in_explore)->toBeFalse();
    expect($user->open_following)->toBeTrue();
    expect($user->privacy_display_last_seen)->toBeFalse();
    expect($user->name)->toBe('Original Name');
});

it('clears notification preferences when an empty payload is provided', function (): void {
    $user = User::factory()->create([
        'notification_preferences' => [
            'new_follower' => true,
        ],
    ]);

    app(SettingsService::class)->saveNotificationPreferences($user, []);

    $user->refresh();

    expect($user->notification_preferences)->toBe([]);
});

it('casts notification preference values to booleans', function (): void {
    $user = User::factory()->create([
        'notification_preferences' => [],
    ]);

    app(SettingsService::class)->saveNotificationPreferences($user, [
        'new_follower' => '1',
        'message_received' => 0,
        'marketing' => '',
    ]);

    $user->refresh();

    expect($user->notification_preferences)->toMatchArray([
        'new_follower' => true,
        'message_received' => false,
        'marketing' => false,
    ]);
});
