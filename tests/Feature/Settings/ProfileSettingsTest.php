<?php

use App\Models\Identity\User;
use App\Notifications\UsernameChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        ], $overrides);
    }
}

if (! function_exists('profileSettingsCurrentPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function profileSettingsCurrentPayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'display_name' => $user->display_name,
            'username' => $user->username,
            'email' => $user->email,
            'bio' => $user->bio,
            'headline' => $user->headline,
            'pronouns' => $user->pronouns,
            'location' => $user->location,
            'website' => $user->website,
            'social_links' => $user->social_links,
            'birth_date' => $user->birth_date?->toDateString(),
            'gender' => $user->gender,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
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
    expect(Schema::hasColumn('users', 'profile_theme'))->toBeFalse();
});

it('saves each editable profile field independently', function (array $override, string $column, mixed $expected): void {
    $user = User::factory()->create([
        'username' => 'field_tester',
        'display_name' => null,
        'bio' => null,
        'headline' => null,
        'pronouns' => null,
        'location' => null,
        'website' => null,
        'social_links' => null,
        'birth_date' => null,
        'gender' => null,
        'locale' => null,
        'timezone' => null,
    ]);

    $this->actingAs($user)
        ->put(route('settings.profile.update'), profileSettingsCurrentPayload($user, $override))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile'));

    $user->refresh();
    $actual = $column === 'birth_date'
        ? $user->birth_date?->toDateString()
        : $user->getAttribute($column);

    expect($actual)->toEqual($expected);
})->with([
    'name' => [['name' => 'Mira Stone'], 'name', 'Mira Stone'],
    'email' => [['email' => 'MIRA@example.test'], 'email', 'mira@example.test'],
    'username' => [['username' => 'mira_profile', 'username_confirm' => 'field_tester'], 'username', 'mira_profile'],
    'display name' => [['display_name' => 'Mira & Milo'], 'display_name', 'Mira & Milo'],
    'bio' => [['bio' => 'A profile bio saved from the edit surface.'], 'bio', 'A profile bio saved from the edit surface.'],
    'headline' => [['headline' => 'Weekend foster volunteer'], 'headline', 'Weekend foster volunteer'],
    'pronouns' => [['pronouns' => 'she/they'], 'pronouns', 'she/they'],
    'location' => [['location' => 'Kaunas'], 'location', 'Kaunas'],
    'website' => [['website' => 'mira.example'], 'website', 'https://mira.example'],
    'social links' => [['social_links' => ['instagram' => 'instagram.com/mira']], 'social_links', ['instagram' => 'https://instagram.com/mira']],
    'birth date' => [['birth_date' => '1994-05-20'], 'birth_date', '1994-05-20'],
    'gender' => [['gender' => 'prefer_not_to_say'], 'gender', 'prefer_not_to_say'],
    'locale' => [['locale' => 'lt_LT'], 'locale', 'lt_LT'],
    'timezone' => [['timezone' => 'Europe/Vilnius'], 'timezone', 'Europe/Vilnius'],
]);

it('does not render profile theme controls in settings', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.profile'))
        ->assertOk()
        ->assertDontSee('Profile theme')
        ->assertDontSee('name="profile_theme"', false);
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

it('rejects invalid profile image uploads', function (string $field, string $case): void {
    Storage::fake((string) config('media-library.disk_name'));

    $user = User::factory()->create();
    $file = match ([$field, $case]) {
        ['avatar', 'type'] => UploadedFile::fake()->create('avatar.pdf', 12, 'application/pdf'),
        ['avatar', 'size'] => UploadedFile::fake()->image('avatar.jpg')->size(10241),
        ['cover', 'type'] => UploadedFile::fake()->create('cover.pdf', 12, 'application/pdf'),
        ['cover', 'size'] => UploadedFile::fake()->image('cover.jpg', 1600, 480)->size(5121),
    };

    $this->actingAs($user)
        ->from(route('settings.profile'))
        ->put(route('settings.profile.update'), profileSettingsCurrentPayload($user, [
            $field => $file,
        ]))
        ->assertSessionHasErrors([$field])
        ->assertRedirect(route('settings.profile'));

    $collection = $field === 'avatar'
        ? User::MEDIA_COLLECTION_AVATAR
        : User::MEDIA_COLLECTION_COVER;

    expect($user->refresh()->getFirstMedia($collection))->toBeNull();
})->with([
    'avatar file type' => ['avatar', 'type'],
    'avatar file size' => ['avatar', 'size'],
    'cover file type' => ['cover', 'type'],
    'cover file size' => ['cover', 'size'],
]);
