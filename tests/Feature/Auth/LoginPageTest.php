<?php

use App\Enums\AccountStatus;
use App\Mail\Auth\MagicLoginLinkMail;
use App\Mail\Auth\PasswordResetLinkMail;
use App\Models\Identity\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

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
        ->assertSee('Send me a login link')
        ->assertSee('data-ui="inline-magic-login-form"', false)
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
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'inline-reset@example.com',
    ]);

    Livewire::test('pages.auth.login')
        ->set('resetEmail', 'INLINE-RESET@EXAMPLE.COM')
        ->call('sendPasswordResetLink')
        ->assertSet('resetEmail', 'inline-reset@example.com')
        ->assertSet('resetStatusMessage', 'If an account with that email exists, you will receive a password reset link shortly.')
        ->assertDispatched('password-reset-link-sent');

    Mail::assertQueued(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->user->is($user)
            && str_contains($mail->resetUrl, '/reset-password/');
    });

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->value('token_hash'))
        ->toBeString()
        ->toHaveLength(64);

    Livewire::test('pages.auth.login')
        ->set('resetEmail', 'missing-reset@example.com')
        ->call('sendPasswordResetLink')
        ->assertSet('resetStatusMessage', 'If an account with that email exists, you will receive a password reset link shortly.')
        ->assertDispatched('password-reset-link-sent');
});

it('requests magic login links from the inline login panel without account enumeration', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'inline-magic@example.com',
    ]);

    Livewire::test('pages.auth.login')
        ->set('magicEmail', 'INLINE-MAGIC@EXAMPLE.COM')
        ->call('sendMagicLoginLink')
        ->assertSet('magicEmail', 'inline-magic@example.com')
        ->assertSet('magicStatusMessage', 'If an account with that email exists, you will receive a login link shortly.')
        ->assertDispatched('magic-login-link-sent');

    Mail::assertQueued(MagicLoginLinkMail::class, function (MagicLoginLinkMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->user->is($user)
            && str_contains($mail->loginUrl, '/magic-login/');
    });

    expect(DB::table('magic_link_tokens')->where('user_id', $user->id)->value('token_hash'))
        ->toBeString()
        ->toHaveLength(64);

    Livewire::test('pages.auth.login')
        ->set('magicEmail', 'missing-magic@example.com')
        ->call('sendMagicLoginLink')
        ->assertSet('magicStatusMessage', 'If an account with that email exists, you will receive a login link shortly.')
        ->assertDispatched('magic-login-link-sent');

    Mail::assertQueued(MagicLoginLinkMail::class, 1);
});

it('keeps magic login link requests generic when mail delivery fails', function (): void {
    Mail::shouldReceive('to')
        ->once()
        ->andThrow(new TransportException('Native mail failed'));

    $user = User::factory()->create([
        'email' => 'inline-magic-failure@example.com',
    ]);

    Livewire::test('pages.auth.login')
        ->set('magicEmail', 'INLINE-MAGIC-FAILURE@EXAMPLE.COM')
        ->call('sendMagicLoginLink')
        ->assertSet('magicEmail', 'inline-magic-failure@example.com')
        ->assertSet('magicStatusMessage', 'If an account with that email exists, you will receive a login link shortly.')
        ->assertDispatched('magic-login-link-sent');

    expect(DB::table('magic_link_tokens')->where('user_id', $user->id)->value('token_hash'))
        ->toBeString()
        ->toHaveLength(64);

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->getKey(),
        'event_type' => 'magic_link_requested',
    ]);
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
