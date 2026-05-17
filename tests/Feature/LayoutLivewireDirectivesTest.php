<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('does not render livewire asset directives as plain text', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('explore.index'))
        ->assertSuccessful()
        ->assertDontSee('@livewireStyles')
        ->assertDontSee('@livewireScripts')
        ->assertDontSee('livewireStyles')
        ->assertDontSee('livewireScripts')
        ->assertDontSee('<livewire:styles', false)
        ->assertDontSee('<livewire:scripts', false);
});
