<?php

namespace App\Enums;

enum PostStatus: string
{
    case Draft = 'draft';

    case Scheduled = 'scheduled';

    case Published = 'published';

    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Private work in progress that is not visible in feeds.',
            self::Scheduled => 'Queued for automatic publishing at a future time.',
            self::Published => 'Visible wherever post privacy rules allow.',
            self::Archived => 'Retained for history but removed from active feeds.',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'amber',
            self::Published => 'emerald',
            self::Archived => 'slate',
        };
    }

    public function isPubliclyReachable(): bool
    {
        return $this === self::Published;
    }

    public function shouldHavePublishedAt(): bool
    {
        return in_array($this, [self::Scheduled, self::Published, self::Archived], true);
    }

    public function clearsPublishedAt(): bool
    {
        return $this === self::Draft;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
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
            static function (array $options, self $status): array {
                $options[$status->value] = $status->label();

                return $options;
            },
            [],
        );
    }
}
