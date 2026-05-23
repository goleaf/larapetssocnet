<?php

use App\Enums\AccountStatus;
use App\Models\Identity\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('routes the login page to the full page Livewire component', function (): void {
    $route = Route::getRoutes()->getByName('login');

    expect($route)->not->toBeNull()
        ->and($route?->gatherMiddleware())->toContain('guest')
        ->and($route?->getAction('livewire_component'))->toBe('pages.auth.login');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-ui="login-page"', false)
        ->assertSee('data-ui="login-form"', false)
        ->assertSee('Email or username')
        ->assertSee('placeholder="Email or username"', false)
        ->assertSee('data-ui="inline-password-reset-form"', false);
});

it('redirects authenticated users away from the login page to the feed', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('feed.index'));
});

it('authenticates users from the Livewire login page with email or username credentials', function (): void {
    $emailUser = User::factory()->create([
        'email' => 'login-livewire@example.com',
    ]);

    Livewire::test('pages.auth.login')
        ->set('credential', '  LOGIN-LIVEWIRE@EXAMPLE.COM  ')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($emailUser);
    auth()->logout();

    $usernameUser = User::factory()->create([
        'username' => 'livewire_login',
    ]);

    Livewire::test('pages.auth.login')
        ->set('credential', 'Livewire_Login')
        ->set('password', 'password')
        ->call('authenticate')
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($usernameUser);
});

it('uses the generic credential failure message without revealing account existence', function (): void {
    $user = User::factory()->create([
        'email' => 'generic-login@example.com',
        'username' => 'generic_login',
    ]);

    Livewire::test('pages.auth.login')
        ->set('credential', $user->email)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['credential']);

    $this->assertGuest();

    Livewire::test('pages.auth.login')
        ->set('credential', 'missing-account@example.com')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['credential']);

    $this->assertGuest();
});

it('starts a progressive lockout countdown after repeated failed attempts', function (): void {
    $user = User::factory()->create([
        'email' => 'lockout-livewire@example.com',
    ]);

    for ($attempt = 0; $attempt < 4; $attempt++) {
        Livewire::test('pages.auth.login')
            ->set('credential', $user->email)
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['credential']);
    }

    $component = Livewire::test('pages.auth.login')
        ->set('credential', $user->email)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['credential'])
        ->assertDispatched('login-lockout-started');

    expect($user->refresh()->failed_login_attempts)->toBe(5)
        ->and($component->get('lockoutSeconds'))->toBeGreaterThan(0)
        ->and($component->get('lockoutMessage'))->toBe('Too many failed login attempts. Please wait 1 minute before trying again.');

    Livewire::test('pages.auth.login')
        ->set('credential', $user->email)
        ->set('password', 'password')
        ->call('authenticate')
        ->assertHasErrors(['credential'])
        ->assertDispatched('login-lockout-started');

    $this->assertGuest();
});

it('requests password reset links from the inline login panel without account enumeration', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'inline-reset@example.com',
    ]);

    Livewire::test('pages.auth.login')
        ->set('resetEmail', 'INLINE-RESET@EXAMPLE.COM')
        ->call('sendPasswordResetLink')
        ->assertSet('resetEmail', 'inline-reset@example.com')
        ->assertSet('resetStatusMessage', 'If an account exists, we sent a password reset link.')
        ->assertDispatched('password-reset-link-sent');

    Notification::assertSentTo($user, ResetPassword::class);

    Livewire::test('pages.auth.login')
        ->set('resetEmail', 'missing-reset@example.com')
        ->call('sendPasswordResetLink')
        ->assertSet('resetStatusMessage', 'If an account exists, we sent a password reset link.')
        ->assertDispatched('password-reset-link-sent');
});

it('rejects enum-only deactivated and suspended accounts with specific messages', function (AccountStatus $status, string $message): void {
    $user = User::factory()->create([
        'account_status' => $status,
        'deactivated_at' => null,
        'suspended_until' => null,
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors([
        'email' => $message,
    ]);

    $this->assertGuest();
})->with([
    'deactivated' => [AccountStatus::Deactivated, 'This account is deactivated. Reactivation is required before signing in.'],
    'suspended' => [AccountStatus::Suspended, 'This account is suspended and cannot sign in right now.'],
]);

it('sets remember me tokens on login and clears them on logout', function (): void {
    $user = User::factory()->create([
        'remember_token' => null,
    ]);
    $rememberCookie = Auth::guard('web')->getRecallerName();

    expect(config('auth.guards.web.remember'))->toBe(43200);

    Livewire::test('pages.auth.login')
        ->set('credential', $user->email)
        ->set('password', 'password')
        ->set('remember', true)
        ->call('authenticate')
        ->assertRedirect(route('dashboard', absolute: false));

    expect($user->refresh()->remember_token)->not->toBeNull();

    $this->post(route('logout'))
        ->assertRedirect('/')
        ->assertCookieExpired($rememberCookie);

    expect($user->refresh()->remember_token)->toBeNull();
    $this->assertGuest();
});
