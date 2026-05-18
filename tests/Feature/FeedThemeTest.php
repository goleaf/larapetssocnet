<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the warm editorial feed surface for missing and invalid theme values', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertSuccessful()
        ->assertSee('data-feed-surface="warm-editorial"', false)
        ->assertSee('Community Feed')
        ->assertDontSee('Feed style')
        ->assertDontSee('Accessible Soft');

    $this->actingAs($user)
        ->get(route('feed.index', ['theme' => 'unknown-theme']))
        ->assertSuccessful()
        ->assertSee('data-feed-surface="warm-editorial"', false)
        ->assertSee('Feed sources')
        ->assertDontSee('High Contrast');
});

it('keeps feed filters available without rendering competing theme controls', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('feed.index', ['source' => 'people', 'type' => 'photo']))
        ->assertSuccessful()
        ->assertSee('data-feed-surface="warm-editorial"', false)
        ->assertSee('Feed sources')
        ->assertSee('People')
        ->assertSee('Photos')
        ->assertSee('Create a post')
        ->assertDontSee('Minimalist Soothe');
});
