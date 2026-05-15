<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults to accessible soft theme for missing and invalid theme values', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertSuccessful()
        ->assertSee('data-feed-theme="accessible-soft"', false)
        ->assertSee('Community Feed');

    $this->actingAs($user)
        ->get(route('feed.index', ['theme' => 'unknown-theme']))
        ->assertSuccessful()
        ->assertSee('data-feed-theme="accessible-soft"', false)
        ->assertSee('Accessible Soft');
});

it('renders the requested community feed theme', function (string $theme, string $label): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('feed.index', ['theme' => $theme]))
        ->assertSuccessful()
        ->assertSee('data-feed-theme="'.$theme.'"', false)
        ->assertSee($label)
        ->assertSee('Create a post');
})->with([
    'accessible soft' => ['accessible-soft', 'Accessible Soft'],
    'high contrast' => ['high-contrast', 'High Contrast'],
    'minimalist soothe' => ['minimalist-soothe', 'Minimalist Soothe'],
]);
