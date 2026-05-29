<?php

use App\Models\Content\PostDraft;
use App\Models\Identity\User;
use App\Services\PostDraftService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates and updates one serialized draft per user', function (): void {
    $user = User::factory()->create();
    $service = app(PostDraftService::class);

    $first = $service->autosave($user, [
        'text_content' => 'First draft body',
        'selected_visibility' => 'followers',
        'selected_pet_ids' => [10, 11],
        'attachment_metadata' => [
            [
                'temporary_path' => 'livewire-tmp/first',
                'file_name' => 'first.jpg',
            ],
        ],
    ], 'profile', 42);

    $second = $service->autosave($user, [
        'text_content' => 'Updated draft body',
        'selected_visibility' => 'private',
        'selected_mood' => 'playful',
        'selected_pet_ids' => [11],
        'scheduled_publish_at' => now('UTC')->addDay()->toIso8601String(),
    ], 'pet-profile', 99);

    expect(PostDraft::query()->count())->toBe(1)
        ->and($second->id)->toBe($first->id)
        ->and($second->context_type)->toBe('pet-profile')
        ->and($second->context_id)->toBe(99)
        ->and($second->body)->toBe('Updated draft body')
        ->and($second->visibility)->toBe('private')
        ->and($second->mood)->toBe('playful')
        ->and($second->tagged_pets)->toBe([11])
        ->and($second->state['text_content'])->toBe('Updated draft body')
        ->and($second->state['selected_visibility'])->toBe('private')
        ->and($second->state['selected_pet_ids'])->toBe([11]);
});

it('restores state and clears the user draft regardless of context', function (): void {
    $user = User::factory()->create();
    $service = app(PostDraftService::class);

    $service->autosave($user, [
        'text_content' => 'Recover this composer text',
        'location_display_text' => 'Vilnius',
    ], 'modal', 7);

    $restored = $service->restore($user, 'feed', 0);
    expect($restored)->not->toBeNull();
    assert($restored instanceof PostDraft);

    $state = $service->stateFor($restored);
    $cleared = $service->clear($user, 'feed', 0);

    expect($state['text_content'])->toBe('Recover this composer text')
        ->and($state['location_display_text'])->toBe('Vilnius')
        ->and($state['context_type'])->toBe('modal')
        ->and($state['context_id'])->toBe(7)
        ->and($cleared)->toBe(1)
        ->and($service->restore($user, 'modal', 7))->toBeNull();
});
