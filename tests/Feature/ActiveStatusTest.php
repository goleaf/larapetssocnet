<?php

use App\Models\Identity\User;
use App\Services\ActiveStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;

uses(RefreshDatabase::class);

if (! class_exists(ActiveStatusProbe::class)) {
    class ActiveStatusProbe extends Component
    {
        public function render(): string
        {
            return '<div>active status probe</div>';
        }
    }
}

beforeEach(function (): void {
    Carbon::setTestNow('2026-05-23 12:00:00');

    if (! $this->app->providerIsLoaded(LivewireServiceProvider::class)) {
        $this->app->register(LivewireServiceProvider::class);
    }
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('updates the authenticated users last active timestamp when a livewire component mounts', function (): void {
    $viewer = User::factory()->create([
        'last_active_at' => null,
        'privacy_display_last_seen' => true,
    ]);

    Livewire::actingAs($viewer)
        ->test(ActiveStatusProbe::class)
        ->assertSee('active status probe');

    expect($viewer->refresh()->last_active_at?->toDateTimeString())->toBe('2026-05-23 12:00:00');
});

it('does not rewrite the last active timestamp when it is less than sixty seconds old', function (): void {
    $viewer = User::factory()->create([
        'last_active_at' => now()->subSeconds(30),
    ]);

    app(ActiveStatusService::class)->touch($viewer);

    expect($viewer->refresh()->last_active_at?->toDateTimeString())->toBe('2026-05-23 11:59:30');
});

it('refreshes the last active timestamp when it is more than sixty seconds old', function (): void {
    $viewer = User::factory()->create([
        'last_active_at' => now()->subSeconds(61),
    ]);

    app(ActiveStatusService::class)->touch($viewer);

    expect($viewer->refresh()->last_active_at?->toDateTimeString())->toBe('2026-05-23 12:00:00');
});

it('shows active status dots only for users who are active and allow the indicator', function (): void {
    $activeUser = User::factory()->create([
        'last_active_at' => now()->subMinutes(4),
        'privacy_display_last_seen' => true,
    ]);
    $inactiveUser = User::factory()->create([
        'last_active_at' => now()->subMinutes(6),
        'privacy_display_last_seen' => true,
    ]);
    $privateUser = User::factory()->create([
        'last_active_at' => now(),
        'privacy_display_last_seen' => false,
    ]);

    expect(Blade::render('<x-ui.avatar :user="$user" size="md" />', ['user' => $activeUser]))
        ->toContain('data-ui="active-status-indicator"')
        ->and(Blade::render('<x-ui.avatar :user="$user" size="md" />', ['user' => $inactiveUser]))
        ->not->toContain('data-ui="active-status-indicator"')
        ->and(Blade::render('<x-ui.avatar :user="$user" :online="true" size="md" />', ['user' => $privateUser]))
        ->not->toContain('data-ui="active-status-indicator"');
});
