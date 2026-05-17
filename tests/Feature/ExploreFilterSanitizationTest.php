<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('falls back to all type and clears empty search', function (): void {
    $response = $this->actingAs(User::factory()->create())->get(route('explore.index', [
        'type' => 'unsupported',
        'q' => '   ',
    ]));

    $response
        ->assertOk()
        ->assertViewHas('type', 'all')
        ->assertViewHas('search', '');
});

it('keeps supported explore type and trimmed search query', function (): void {
    $response = $this->actingAs(User::factory()->create())->get(route('explore.index', [
        'type' => 'videos',
        'q' => '  puppies  ',
    ]));

    $response
        ->assertOk()
        ->assertViewHas('type', 'videos')
        ->assertViewHas('search', 'puppies');
});
