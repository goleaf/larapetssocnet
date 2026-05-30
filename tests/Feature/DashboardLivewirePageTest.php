<?php

use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('serves the authenticated dashboard through a class based Livewire page', function (): void {
    $route = Route::getRoutes()->getByName('dashboard');

    expect($route)->not->toBeNull()
        ->and($route?->getAction('livewire_component'))->toBe('pages.dashboard.index')
        ->and($route?->gatherMiddleware())->toContain('auth.verified')
        ->and($route?->gatherMiddleware())->toContain('banned')
        ->and($route?->gatherMiddleware())->toContain('track_last_seen');
});

it('renders the dashboard from computed component data', function (): void {
    $user = User::factory()->create([
        'name' => 'Mira Stone',
        'username' => 'mira_stone',
    ]);

    Livewire::actingAs($user)
        ->test('pages.dashboard.index')
        ->assertSee('Hi Mira, keep your pet world moving.')
        ->assertSee('Share an update')
        ->assertSee('Find groups')
        ->assertSee('Community paths')
        ->assertDontSee("You're logged in!");
});

it('keeps dashboard template free of setup php and removes the old controller view', function (): void {
    $source = (string) file_get_contents(resource_path('views/livewire/pages/dashboard/index.blade.php'));

    expect($source)
        ->not->toContain('@php')
        ->not->toContain('auth()->user()')
        ->and(View::exists('dashboard.index'))->toBeFalse();
});

it('renders the dashboard route with bounded queries', function (): void {
    $user = User::factory()->create([
        'name' => 'Mira Stone',
        'username' => 'mira_stone',
    ]);

    $this->assertQueryCount(28, function () use ($user): void {
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Quick actions');
    });
});
