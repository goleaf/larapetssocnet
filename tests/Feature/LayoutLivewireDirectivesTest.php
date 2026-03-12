<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('does not render livewire asset directives as plain text', function (): void {
    $this->get(route('explore.index'))
        ->assertSuccessful()
        ->assertDontSee('@livewireStyles')
        ->assertDontSee('@livewireScripts');
});
