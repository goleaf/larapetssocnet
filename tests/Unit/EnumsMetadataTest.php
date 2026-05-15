<?php

use App\Enums\FollowAbility;
use App\Enums\GroupMemberRole;
use App\Enums\GroupMemberStatus;
use App\Enums\MessageStatus;
use App\Enums\PostStatus;
use App\Enums\ProfileVisibility;

it('exposes labels and option maps for backed enums', function (string $enumClass): void {
    $options = $enumClass::options();

    expect($options)->not->toBeEmpty();

    foreach ($enumClass::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty()
            ->and($case->description())->toBeString()->not->toBeEmpty()
            ->and($options)->toHaveKey($case->value, $case->label());
    }
})->with([
    GroupMemberRole::class,
    GroupMemberStatus::class,
    MessageStatus::class,
    PostStatus::class,
    ProfileVisibility::class,
]);

it('centralizes group role hierarchy information', function (): void {
    expect(GroupMemberRole::Owner->rank())->toBeGreaterThan(GroupMemberRole::Admin->rank())
        ->and(GroupMemberRole::Admin->isManager())->toBeTrue()
        ->and(GroupMemberRole::Moderator->canModerate())->toBeTrue()
        ->and(GroupMemberRole::Admin->canManage(GroupMemberRole::Moderator))->toBeTrue()
        ->and(GroupMemberRole::Moderator->nextPromotion())->toBe(GroupMemberRole::Admin)
        ->and(GroupMemberRole::Admin->nextDemotion())->toBe(GroupMemberRole::Moderator)
        ->and(GroupMemberRole::managerValues())->toBe(['owner', 'admin']);
});

it('centralizes group member status behavior', function (): void {
    expect(GroupMemberStatus::Active->isActive())->toBeTrue()
        ->and(GroupMemberStatus::Accepted->isActive())->toBeTrue()
        ->and(GroupMemberStatus::Pending->isPending())->toBeTrue()
        ->and(GroupMemberStatus::Banned->isBanned())->toBeTrue()
        ->and(GroupMemberStatus::Banned->isTerminal())->toBeTrue()
        ->and(GroupMemberStatus::activeValues())->toBe(['active', 'accepted']);
});

it('describes post and message state transitions', function (): void {
    expect(PostStatus::Published->isPubliclyReachable())->toBeTrue()
        ->and(PostStatus::Draft->clearsPublishedAt())->toBeTrue()
        ->and(PostStatus::Scheduled->shouldHavePublishedAt())->toBeTrue()
        ->and(MessageStatus::Read->isAtLeast(MessageStatus::Delivered))->toBeTrue()
        ->and(MessageStatus::Sent->isRead())->toBeFalse();
});

it('describes follow gates and profile privacy levels', function (): void {
    expect(FollowAbility::Follow->policyMethod())->toBe('follow')
        ->and(FollowAbility::ViewFollowers->isMutation())->toBeFalse()
        ->and(FollowAbility::options())->toHaveKey('Follow', 'Follow')
        ->and(ProfileVisibility::Private->isMoreRestrictiveThan(ProfileVisibility::Public))->toBeTrue()
        ->and(ProfileVisibility::Public->allowsGuestProfile())->toBeTrue()
        ->and(ProfileVisibility::FollowersOnly->marksAccountPrivate())->toBeTrue();
});
