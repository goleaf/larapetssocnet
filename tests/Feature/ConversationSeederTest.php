<?php

use App\Enums\MessageStatus;
use App\Models\Identity\User;
use App\Models\Messaging\Conversation;
use App\Models\Messaging\Message;
use Database\Seeders\ConversationSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds message-ready inbox threads for every user', function (): void {
    $this->seed(UserSeeder::class);
    $this->seed(ConversationSeeder::class);

    $users = User::query()->select(['id'])->get();

    expect(Conversation::query()->count())->toBeGreaterThanOrEqual($users->count());

    foreach ($users as $user) {
        $hasMessages = Message::query()->forUser($user->getKey())->exists();

        expect($hasMessages)->toBeTrue();
    }

    expect(Message::query()->whereNull('receiver_id')->count())->toBe(0);
    expect(Message::query()->where('status', MessageStatus::Delivered->value)->count())->toBeGreaterThan(0);
});
