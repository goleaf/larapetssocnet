<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('updates privacy settings to private and disables explore', function (): void {
    $user = User::factory()->create([
        'profile_visibility' => 'public',
        'is_private' => false,
        'show_in_explore' => true,
        'open_following' => true,
    ]);

    $this->actingAs($user)
        ->put(route('settings.privacy.update'), [
            'profile_visibility' => 'private',
            'messaging_permission' => 'followers_only',
            'pets_visibility' => 'followers_only',
            'groups_visibility' => 'followers_only',
            'show_in_explore' => true,
            'open_following' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.privacy'));

    $user->refresh();

    expect($user->profile_visibility)->toBe('private');
    expect((bool) $user->is_private)->toBeTrue();
    expect((bool) $user->show_in_explore)->toBeFalse();
    expect((bool) $user->open_following)->toBeFalse();
});

it('privacy updates do not overwrite profile fields', function (): void {
    $user = User::factory()->create([
        'display_name' => 'Profile Name',
        'profile_visibility' => 'public',
    ]);

    $this->actingAs($user)
        ->put(route('settings.privacy.update'), [
            'profile_visibility' => 'followers_only',
            'messaging_permission' => 'everyone',
            'pets_visibility' => 'everyone',
            'groups_visibility' => 'everyone',
            'show_in_explore' => true,
            'open_following' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->display_name)->toBe('Profile Name');
});
