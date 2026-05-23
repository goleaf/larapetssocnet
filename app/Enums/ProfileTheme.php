<?php

namespace App\Enums;

enum ProfileTheme: string
{
    case WarmEditorial = 'warm_editorial';

    case Meadow = 'meadow';

    case Lagoon = 'lagoon';

    case Marigold = 'marigold';

    case Berry = 'berry';

    public static function default(): self
    {
        return self::fromValue((string) config('profile_themes.default')) ?? self::WarmEditorial;
    }

    public static function fromValue(?string $value): ?self
    {
        if (! $value) {
            return null;
        }

        return self::tryFrom($value);
    }

    public function label(): string
    {
        return (string) ($this->definition()['label'] ?? str($this->name)->headline());
    }

    public function description(): string
    {
        return (string) ($this->definition()['description'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $definition = config('profile_themes.themes.'.$this->value, []);

        return is_array($definition) ? $definition : [];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $theme): string => $theme->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $theme): array {
                $options[$theme->value] = $theme->label();

                return $options;
            },
            [],
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function settingsOptions(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $theme): array {
                $definition = $theme->definition();

                $options[$theme->value] = [
                    'label' => $theme->label(),
                    'description' => $theme->description(),
                    'background' => (string) ($definition['background'] ?? ''),
                    'surface' => (string) ($definition['surface'] ?? ''),
                    'accent' => (string) ($definition['accent'] ?? ''),
                    'accent_soft' => (string) ($definition['accent_soft'] ?? ''),
                    'tab_underline' => (string) ($definition['tab_underline'] ?? ($definition['accent'] ?? '')),
                ];

                return $options;
            },
            [],
        );
    }
}
