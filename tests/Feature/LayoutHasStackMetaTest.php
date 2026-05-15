<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders default layout meta when meta stack is empty', function (): void {
    $this->get(route('explore.index'))
        ->assertSuccessful()
        ->assertSee(
            '<meta name="description" content="PetSocial is a community for sharing pet moments, care tips, and adoption stories.">',
            false
        );
});

it('renders stacked meta tags when view pushes meta content', function (): void {
    $user = User::factory()->create([
        'name' => 'Meta Owner',
        'username' => 'metaowner',
        'is_private' => false,
    ]);

    $this->get(route('profile.show', ['user' => $user]))
        ->assertSuccessful()
        ->assertSee('<meta property="og:type" content="profile">', false)
        ->assertDontSee(
            '<meta name="description" content="PetSocial is a community for sharing pet moments, care tips, and adoption stories.">',
            false
        );
});
