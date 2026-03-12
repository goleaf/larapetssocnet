<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Blaze\Config;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('renders anonymous Blade components with Blaze optimization enabled', function (): void {
    $blazeConfig = app(Config::class);

    expect($blazeConfig->shouldCompile(resource_path('views/components/ui/button.blade.php')))->toBeBool();

    $primaryButton = Blade::render('<x-ui.button>Save</x-ui.button>');
    $avatar = Blade::render('<x-ui.avatar name="Jane Doe" size="sm" />');

    expect($primaryButton)
        ->toContain('Save')
        ->toContain('btn-base')
        ->toContain('bg-paw')
        ->toContain('data-ui-control="button"');

    expect($avatar)
        ->toContain('JD')
        ->toContain('h-8 w-8');
});

it('renders section-based views inside the canonical app layout shell', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertSuccessful()
        ->assertSee('No contests yet. Be the first to create one!')
        ->assertSee('min-h-screen bg-cream', false);
});
