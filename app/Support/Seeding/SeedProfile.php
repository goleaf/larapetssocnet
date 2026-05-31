<?php

declare(strict_types=1);

namespace App\Support\Seeding;

use Illuminate\Foundation\Application;

enum SeedProfile: string
{
    case Tiny = 'tiny';

    case Demo = 'demo';

    case Test = 'test';

    case Performance = 'performance';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function resolve(?string $value): ?self
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return null;
        }

        return self::tryFrom($normalized);
    }

    public static function fromConfig(): ?self
    {
        return self::resolve((string) config('seeding.profile'));
    }

    public function isPerformance(): bool
    {
        return $this === self::Performance;
    }

    public function isAllowedInCurrentEnvironment(Application $app, bool $confirmPerformance): bool
    {
        if (! $this->isPerformance()) {
            return true;
        }

        if ($app->isProduction()) {
            return false;
        }

        if ($app->environment('local', 'testing')) {
            return true;
        }

        return $confirmPerformance;
    }

    public function users(): int
    {
        return (int) $this->setting('users', 0);
    }

    public function pets(): int
    {
        return (int) $this->setting('pets', 0);
    }

    public function posts(): int
    {
        return (int) $this->setting('posts', 0);
    }

    public function comments(): int
    {
        return (int) $this->setting('comments', 0);
    }

    public function likes(): int
    {
        return (int) $this->setting('likes', 0);
    }

    public function contentPosts(): int
    {
        return (int) $this->setting('content_posts', 0);
    }

    public function adoptablePets(): int
    {
        return (int) $this->setting('adoptable_pets', 0);
    }

    public function followsPerUser(): int
    {
        return (int) $this->setting('social.follows_per_user', 0);
    }

    public function petFollowsPerUser(): int
    {
        return (int) $this->setting('social.pet_follows_per_user', 0);
    }

    public function blocks(): int
    {
        return (int) $this->setting('social.blocks', 0);
    }

    public function mediaEnabled(): bool
    {
        return (bool) $this->setting('media.enabled', false);
    }

    public function usersWithMedia(): int
    {
        return (int) $this->setting('media.users_with_media', 0);
    }

    public function petsWithMedia(): int
    {
        return (int) $this->setting('media.pets_with_media', 0);
    }

    public function seedHashtags(): bool
    {
        return (bool) $this->setting('seed_hashtags', false);
    }

    public function seedReactionRows(): bool
    {
        return (bool) $this->setting('seed_reaction_rows', false);
    }

    public function seedCountSummary(): array
    {
        return [
            'users' => $this->users(),
            'pets' => $this->pets(),
            'posts' => $this->posts(),
            'comments' => $this->comments(),
            'likes' => $this->likes(),
            'adoptable_pets' => $this->adoptablePets(),
        ];
    }

    private function setting(string $path, mixed $default = null): mixed
    {
        $value = config('seeding.profiles.'.$this->value, []);

        if (! is_array($value)) {
            return $default;
        }

        $parts = explode('.', $path);
        $current = $value;

        foreach ($parts as $part) {
            if (! is_array($current) || ! array_key_exists($part, $current)) {
                return $default;
            }

            $current = $current[$part];
        }

        return $current;
    }
}
