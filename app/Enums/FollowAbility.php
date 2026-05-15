<?php

declare(strict_types=1);

namespace App\Enums;

enum FollowAbility
{
    case Follow;

    case Unfollow;

    case ViewFollowers;

    case ViewFollowing;

    case ManageRequests;

    case RemoveFollower;

    public function policyMethod(): string
    {
        return match ($this) {
            self::Follow => 'follow',
            self::Unfollow => 'unfollow',
            self::ViewFollowers => 'viewFollowers',
            self::ViewFollowing => 'viewFollowing',
            self::ManageRequests => 'manageRequests',
            self::RemoveFollower => 'removeFollower',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Follow => 'Follow',
            self::Unfollow => 'Unfollow',
            self::ViewFollowers => 'View followers',
            self::ViewFollowing => 'View following',
            self::ManageRequests => 'Manage follow requests',
            self::RemoveFollower => 'Remove follower',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Follow => 'Allows a user to start following another profile.',
            self::Unfollow => 'Allows a user to stop following another profile.',
            self::ViewFollowers => 'Allows a user to inspect a profile follower list.',
            self::ViewFollowing => 'Allows a user to inspect profiles followed by another user.',
            self::ManageRequests => 'Allows a user to approve or reject incoming follow requests.',
            self::RemoveFollower => 'Allows a user to remove an existing follower.',
        };
    }

    public function isMutation(): bool
    {
        return match ($this) {
            self::Follow,
            self::Unfollow,
            self::ManageRequests,
            self::RemoveFollower => true,
            self::ViewFollowers,
            self::ViewFollowing => false,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            static function (array $options, self $ability): array {
                $options[$ability->name] = $ability->label();

                return $options;
            },
            [],
        );
    }
}
