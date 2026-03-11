<?php

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('filters unread notifications', function (): void {
    $user = User::factory()->create();

    $unreadNotification = Notification::factory()->create([
        'notifiable_id' => $user->getKey(),
        'read_at' => null,
    ]);

    $readNotification = Notification::factory()->create([
        'notifiable_id' => $user->getKey(),
        'read_at' => now(),
    ]);

    $notificationIds = Notification::query()
        ->unread()
        ->pluck('notifications.id');

    expect($notificationIds)
        ->toContain($unreadNotification->getKey())
        ->not->toContain($readNotification->getKey());
});

it('filters notifications by user id', function (): void {
    $targetUser = User::factory()->create();
    $otherUser = User::factory()->create();

    $targetNotification = Notification::factory()->create([
        'notifiable_id' => $targetUser->getKey(),
    ]);

    $otherNotification = Notification::factory()->create([
        'notifiable_id' => $otherUser->getKey(),
    ]);

    $notificationIds = Notification::query()
        ->forUser($targetUser->getKey())
        ->pluck('notifications.id');

    expect($notificationIds)
        ->toContain($targetNotification->getKey())
        ->not->toContain($otherNotification->getKey());
});
