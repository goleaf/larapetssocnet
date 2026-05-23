<?php

use App\Enums\ProfileTheme;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defines exactly five accessible profile themes', function (): void {
    $themes = config('profile_themes.themes');

    expect($themes)->toBeArray()
        ->toHaveCount(5)
        ->and(array_keys($themes))->toEqual(ProfileTheme::values())
        ->and(config('profile_themes.default'))->toBe(ProfileTheme::WarmEditorial->value);

    foreach ($themes as $themeName => $definition) {
        expect($definition)->toHaveKeys([
            'label',
            'description',
            'background',
            'surface',
            'text',
            'muted',
            'accent',
            'accent_hover',
            'accent_soft',
            'on_accent',
            'tab_underline',
            'texture',
        ]);

        foreach (config('profile_themes.contrast_requirements') as $requirement) {
            $ratio = profileThemeContrastRatio(
                $definition[$requirement['foreground']],
                $definition[$requirement['background']],
            );

            expect($ratio)
                ->toBeGreaterThanOrEqual($requirement['minimum'], sprintf(
                    '%s %s on %s contrast is %.2f',
                    $themeName,
                    $requirement['foreground'],
                    $requirement['background'],
                    $ratio,
                ));
        }
    }
});

it('renders the selected profile theme as root css custom properties', function (): void {
    $user = User::factory()->create([
        'name' => 'Theme Owner',
        'username' => 'theme_owner',
        'profile_theme' => ProfileTheme::Berry->value,
        'profile_visibility' => 'public',
        'is_private' => false,
    ]);

    $this->get(route('profile.show', ['user' => $user]))
        ->assertOk()
        ->assertSee('data-ui="profile-shell"', false)
        ->assertSee('data-profile-theme="berry"', false)
        ->assertSee('--profile-theme-background: #fbf1f3', false)
        ->assertSee('--profile-theme-accent: #7b3144', false)
        ->assertSee('--profile-theme-tab-underline: #7b3144', false);
});

it('shows theme choices in profile settings and stores the selected enum value', function (): void {
    $user = User::factory()->create([
        'profile_theme' => ProfileTheme::WarmEditorial->value,
    ]);

    $this->actingAs($user)
        ->get(route('settings.profile'))
        ->assertOk()
        ->assertSee('data-ui="profile-theme-section"', false)
        ->assertSee('name="profile_theme"', false)
        ->assertSee(ProfileTheme::Meadow->label());

    $this->actingAs($user)
        ->put(route('settings.profile.update'), profileThemeSettingsPayload($user, [
            'profile_theme' => ProfileTheme::Lagoon->value,
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.profile'));

    expect($user->refresh()->profile_theme)->toBe(ProfileTheme::Lagoon);
});

it('rejects profile theme values outside the configured enum', function (): void {
    $user = User::factory()->create([
        'profile_theme' => ProfileTheme::WarmEditorial->value,
    ]);

    $this->actingAs($user)
        ->from(route('settings.profile'))
        ->put(route('settings.profile.update'), profileThemeSettingsPayload($user, [
            'profile_theme' => 'neon_lime',
        ]))
        ->assertSessionHasErrors(['profile_theme'])
        ->assertRedirect(route('settings.profile'));

    expect($user->refresh()->profile_theme)->toBe(ProfileTheme::WarmEditorial);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function profileThemeSettingsPayload(User $user, array $overrides = []): array
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
        'profile_theme' => $user->profileTheme()->value,
        'birth_date' => $user->birth_date?->toDateString(),
        'gender' => $user->gender,
        'locale' => $user->locale,
        'timezone' => $user->timezone,
    ], $overrides);
}

function profileThemeContrastRatio(string $foreground, string $background): float
{
    $foregroundLuminance = profileThemeRelativeLuminance($foreground);
    $backgroundLuminance = profileThemeRelativeLuminance($background);

    $lighter = max($foregroundLuminance, $backgroundLuminance);
    $darker = min($foregroundLuminance, $backgroundLuminance);

    return ($lighter + 0.05) / ($darker + 0.05);
}

function profileThemeRelativeLuminance(string $hex): float
{
    [$red, $green, $blue] = array_map(
        static function (int $channel): float {
            $normalized = $channel / 255;

            return $normalized <= 0.03928
                ? $normalized / 12.92
                : (($normalized + 0.055) / 1.055) ** 2.4;
        },
        profileThemeRgb($hex),
    );

    return (0.2126 * $red) + (0.7152 * $green) + (0.0722 * $blue);
}

/**
 * @return array{0: int, 1: int, 2: int}
 */
function profileThemeRgb(string $hex): array
{
    $hex = ltrim($hex, '#');

    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }

    return [
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
    ];
}
