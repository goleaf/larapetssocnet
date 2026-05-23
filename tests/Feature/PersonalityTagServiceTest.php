<?php

use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Models\Pets\PetTag;
use App\Services\PersonalityTagService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = new PersonalityTagService;
    $this->user = User::factory()->create();
});

it('syncs tags correctly', function (): void {
    $pet = Pet::factory()->create(['user_id' => $this->user->id]);

    $result = $this->service->sync($pet, ['playful', 'energetic', 'calm']);

    expect($result)->toHaveCount(3);
    expect($result)->toContain('playful', 'energetic', 'calm');
    expect($pet->fresh()->personality_tags)->toHaveCount(3);
});

it('enforces max 10 tags', function (): void {
    $pet = Pet::factory()->create(['user_id' => $this->user->id]);

    $tags = [
        'playful',
        'energetic',
        'calm',
        'shy',
        'friendly',
        'independent',
        'cuddly',
        'protective',
        'gentle',
        'silly',
        'stubborn',
        'smart',
    ];

    $result = $this->service->sync($pet, $tags);

    expect($result)->toHaveCount((int) config('pets.personality_tags.max'));
});

it('stores tags as lowercase', function (): void {
    $pet = Pet::factory()->create(['user_id' => $this->user->id]);

    $result = $this->service->sync($pet, ['PLAYFUL', 'Energetic']);

    expect($result)->toBe(['playful', 'energetic']);
});

it('strips special characters from tags', function (): void {
    $pet = Pet::factory()->create(['user_id' => $this->user->id]);

    $result = $this->service->sync($pet, ['super-fast!', 'very@cute']);

    expect($result[0])->toBe('superfast');
    expect($result[1])->toBe('verycute');
});

it('deduplicates tags', function (): void {
    $pet = Pet::factory()->create(['user_id' => $this->user->id]);

    $result = $this->service->sync($pet, ['playful', 'PLAYFUL', 'Playful']);

    expect($result)->toHaveCount(1);
    expect($result[0])->toBe('playful');
});

it('returns correct suggestions list', function (): void {
    $suggestions = $this->service->getSuggestions();

    expect($suggestions)->toBeArray();
    expect($suggestions)->toContain('playful', 'calm', 'adventurous');
    expect($suggestions)->toHaveCount(count((array) config('pets.personality_tags.suggestions')));
});

it('syncs personality tag records for search', function (): void {
    $pet = Pet::factory()->create(['user_id' => $this->user->id]);

    $this->service->sync($pet, ['Playful', 'Calm']);

    $slugs = PetTag::query()
        ->where('pet_id', $pet->getKey())
        ->pluck('slug')
        ->all();

    expect($slugs)->toContain('playful');
    expect($slugs)->toContain('calm');
});
