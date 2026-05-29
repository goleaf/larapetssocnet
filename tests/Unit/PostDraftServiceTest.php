<?php

use App\Models\Content\PostDraft;
use App\Models\Identity\User;
use App\Services\PostDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates and updates one draft per user context', function (): void {
    $user = User::factory()->create();
    $service = app(PostDraftService::class);

    $first = $service->autosave($user, [
        'body' => 'First draft body',
        'visibility' => 'followers',
        'tagged_pets' => [10, 11],
    ], 'profile', 42);

    $second = $service->autosave($user, [
        'body' => 'Updated draft body',
        'visibility' => 'private',
        'mood' => 'playful',
        'tagged_pets' => [11],
    ], 'profile', 42);

    expect(PostDraft::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->body)->toBe('Updated draft body')
        ->and($second->visibility)->toBe('private')
        ->and($second->mood)->toBe('playful')
        ->and($second->tagged_pets)->toBe([11]);
});

it('restores and clears an autosaved draft', function (): void {
    $user = User::factory()->create();
    $service = app(PostDraftService::class);

    $service->autosave($user, [
        'body' => 'Recover this composer text',
        'location' => 'Vilnius',
    ], 'modal', 7);

    $restored = $service->restore($user, 'modal', 7);
    $cleared = $service->clear($user, 'modal', 7);

    expect($restored)
        ->not->toBeNull()
        ->and($restored?->body)->toBe('Recover this composer text')
        ->and($restored?->location)->toBe('Vilnius')
        ->and($cleared)->toBe(1)
        ->and($service->restore($user, 'modal', 7))->toBeNull();
});
