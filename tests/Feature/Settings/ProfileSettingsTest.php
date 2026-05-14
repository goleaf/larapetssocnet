<?php

use App\Models\User;
use App\Notifications\UsernameChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

if (! function_exists('profileSettingsPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function profileSettingsPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'display_name' => 'Display '.$user->name,
            'username' => $user->username,
            'email' => $user->email,
            'bio' => 'Updated bio for profile settings.',
            'headline' => 'Pet parent and proud',
            'pronouns' => 'they/them',
            'location' => 'Vilnius',
            'website' => 'example.test',
            'social_links' => [
                'x' => 'x.com/example',
                'instagram' => 'https://instagram.com/example',
            ],
            'locale' => 'en',
            'timezone' => 'Europe/Vilnius',
            'profile_theme' => 'meadow',
        ], $overrides);
    }
}

it('updates profile settings fields', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.profile.update'), profileSettingsPayload($user))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile'));

    $user->refresh();

    expect($user->display_name)->toBe('Display '.$user->name);
    expect($user->headline)->toBe('Pet parent and proud');
    expect($user->pronouns)->toBe('they/them');
    expect($user->location)->toBe('Vilnius');
    expect($user->website)->toBe('https://example.test');
    expect($user->social_links)->toMatchArray([
        'x' => 'https://x.com/example',
        'instagram' => 'https://instagram.com/example',
    ]);
    expect($user->locale)->toBe('en');
    expect($user->timezone)->toBe('Europe/Vilnius');
    expect($user->profile_theme)->toBe('meadow');
});

it('rejects reserved usernames during profile updates', function (): void {
    $user = User::factory()->create(['username' => 'validname']);

    $this->actingAs($user)
        ->from(route('settings.profile'))
        ->put(route('settings.profile.update'), profileSettingsPayload($user, [
            'username' => 'settings',
            'username_confirm' => 'validname',
        ]))
        ->assertSessionHasErrors(['username'])
        ->assertRedirect(route('settings.profile'));
});

it('rejects numeric-only usernames during profile updates', function (): void {
    $user = User::factory()->create(['username' => 'validname']);

    $this->actingAs($user)
        ->from(route('settings.profile'))
        ->put(route('settings.profile.update'), profileSettingsPayload($user, [
            'username' => '12345',
            'username_confirm' => 'validname',
        ]))
        ->assertSessionHasErrors(['username'])
        ->assertRedirect(route('settings.profile'));
});

it('records username changes only when the username actually changes', function (): void {
    $user = User::factory()->create(['username' => 'before']);

    $this->actingAs($user)
        ->put(route('settings.profile.update'), profileSettingsPayload($user, [
            'username' => 'after',
            'username_confirm' => 'before',
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('username_changes', [
        'user_id' => $user->id,
        'old_username' => 'before',
        'new_username' => 'after',
    ]);

    $this->assertDatabaseHas('notifications', [
        'type' => UsernameChanged::class,
    ]);

    $user->refresh();

    $this->actingAs($user)
        ->put(route('settings.profile.update'), profileSettingsPayload($user))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('username_changes', 1);
});
