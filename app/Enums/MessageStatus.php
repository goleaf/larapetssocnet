<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Sent = 'sent';

    case Delivered = 'delivered';

    case Read = 'read';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Sent => 'Message was created and handed to delivery.',
            self::Delivered => 'Message reached the recipient conversation.',
            self::Read => 'Recipient opened or read the message.',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
        };
    }

    public function isAtLeast(self $status): bool
    {
        return $this->rank() >= $status->rank();
    }

    public function isRead(): bool
    {
        return $this === self::Read;
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
