<?php

use App\Models\Pet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('rejects too many personality tags', function (): void {
    $owner = User::factory()->create();
    $max = (int) config('pets.personality_tags.max', 10);

    $tags = collect(range(1, $max + 1))
        ->map(fn (int $i): string => "tag{$i}")
        ->implode(', ');

    $this->actingAs($owner)
        ->post(route('pets.store'), [
            'name' => 'Tag Limit',
            'species' => 'dog',
            'sex' => 'male',
            'personality_tags' => $tags,
        ])
        ->assertSessionHasErrors(['personality_tags']);
});

it('rejects personality tags that are too long', function (): void {
    $owner = User::factory()->create();
    $maxLength = (int) config('pets.personality_tags.max_length', 30);
    $longTag = str_repeat('a', $maxLength + 1);

    $this->actingAs($owner)
        ->post(route('pets.store'), [
            'name' => 'Long Tag',
            'species' => 'cat',
            'sex' => 'female',
            'personality_tags' => [$longTag],
        ])
        ->assertSessionHasErrors(['personality_tags.0']);
});

it('ignores empty personality tags safely', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('pets.store'), [
            'name' => 'Clean Tags',
            'species' => 'rabbit',
            'sex' => 'unknown',
            'personality_tags' => ' , , ',
        ])
        ->assertRedirect();

    $pet = Pet::query()->where('name', 'Clean Tags')->firstOrFail();

    expect($pet->personality_tags)->toBeArray()->toHaveCount(0);
});

it('accepts visibility input and maps to private pets', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('pets.store'), [
            'name' => 'Private Buddy',
            'species' => 'dog',
            'sex' => 'male',
            'visibility' => 'private',
        ])
        ->assertRedirect();

    $pet = Pet::query()->where('name', 'Private Buddy')->firstOrFail();

    expect((bool) $pet->is_public)->toBeFalse();
});
