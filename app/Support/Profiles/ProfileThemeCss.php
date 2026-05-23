<?php

namespace App\Support\Profiles;

use App\Enums\ProfileTheme;

final class ProfileThemeCss
{
    /**
     * @return array<string, string>
     */
    public static function variables(ProfileTheme|string|null $theme): array
    {
        $resolvedTheme = $theme instanceof ProfileTheme
            ? $theme
            : ProfileTheme::fromValue(is_string($theme) ? $theme : null);

        $definition = ($resolvedTheme ?? ProfileTheme::default())->definition();

        $background = self::themeValue($definition, 'background', '#fbf6ee');
        $surface = self::themeValue($definition, 'surface', '#fffdf8');
        $text = self::themeValue($definition, 'text', '#201914');
        $muted = self::themeValue($definition, 'muted', '#4c4037');
        $accent = self::themeValue($definition, 'accent', '#8f3f24');
        $accentHover = self::themeValue($definition, 'accent_hover', '#6f301b');
        $accentSoft = self::themeValue($definition, 'accent_soft', '#f0d8ca');
        $tabUnderline = self::themeValue($definition, 'tab_underline', $accent);

        return [
            '--profile-theme-background' => $background,
            '--profile-theme-surface' => $surface,
            '--profile-theme-text' => $text,
            '--profile-theme-muted' => $muted,
            '--profile-theme-accent' => $accent,
            '--profile-theme-accent-hover' => $accentHover,
            '--profile-theme-accent-soft' => $accentSoft,
            '--profile-theme-tab-underline' => $tabUnderline,
            '--profile-theme-texture' => self::themeValue($definition, 'texture', 'none'),
            '--color-cream' => $background,
            '--color-warm-white' => $surface,
            '--color-paper-warm' => $accentSoft,
            '--color-bark' => $text,
            '--color-fur' => $muted,
            '--color-whisker' => $muted,
            '--color-paw' => $accent,
            '--color-paw-dark' => $accentHover,
            '--color-paw-light' => $accentSoft,
            '--surface-page' => $background,
            '--surface-panel' => $surface,
            '--surface-muted' => $accentSoft,
            '--ui-bg' => $background,
            '--ui-surface' => $surface,
            '--ui-text' => $text,
            '--ui-text-muted' => $muted,
            '--ui-primary' => $accent,
            '--ui-primary-strong' => $accentHover,
            '--accent' => $accent,
            '--accent-hover' => $accentHover,
            '--shadow-focus' => '0 0 0 4px color-mix(in oklab, '.$accent.' 24%, transparent)',
        ];
    }

    public static function inlineStyle(ProfileTheme|string|null $theme): string
    {
        return collect(self::variables($theme))
            ->map(fn (string $value, string $property): string => $property.': '.$value)
            ->implode('; ');
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private static function themeValue(array $definition, string $key, string $fallback): string
    {
        $value = $definition[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
