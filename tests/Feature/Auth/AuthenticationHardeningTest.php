<?php

use App\Models\Identity\User;
use App\Models\Security\AuthAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('requires age confirmation terms and a non-common password at registration', function (): void {
    $underageDate = now()->subYears(10);

    $this->post('/register', [
        'name' => 'Young User',
        'email' => 'young@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'birth_day' => $underageDate->day,
        'birth_month' => $underageDate->month,
        'birth_year' => $underageDate->year,
    ])
        ->assertSessionHasErrors(['password', 'birth_date', 'terms']);

    expect(User::query()->where('email', 'young@example.com')->exists())->toBeFalse();
});

it('silently rejects honeypot registrations without creating an account', function (): void {
    $birthDate = now()->subYears(24);

    $this->post('/register', [
        'name' => 'Bot User',
        'username' => 'bot_user',
        'email' => 'bot@example.com',
        'password' => 'PetSocial2026!',
        'password_confirmation' => 'PetSocial2026!',
        'birth_day' => $birthDate->day,
        'birth_month' => $birthDate->month,
        'birth_year' => $birthDate->year,
        'terms' => '1',
        'company_name' => 'Acme Bot',
    ])
        ->assertRedirect(route('login', absolute: false))
        ->assertSessionHas('status');

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'bot@example.com']);
    $this->assertDatabaseHas('auth_audit_logs', ['event_type' => 'registration_honeypot']);
});

it('records successful and failed login attempts without revealing which credential failed', function (): void {
    $user = User::factory()->create([
        'username' => 'audit_user',
        'email' => 'audit@example.com',
    ]);

    $this->post('/login', [
        'email' => 'audit_user',
        'password' => 'wrong-password',
    ])
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();
    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_failure',
    ]);

    $this->post('/login', [
        'email' => 'audit_user',
        'password' => 'password',
    ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_success',
    ]);
});

it('rejects banned users with valid credentials and records the blocked login attempt', function (): void {
    $user = User::factory()->create([
        'email' => 'banned-login@example.com',
        'is_banned' => true,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertRedirect(route('banned', absolute: false));

    $this->assertGuest();

    $auditLog = AuthAuditLog::query()
        ->where('user_id', $user->id)
        ->where('event_type', 'login_failure')
        ->firstOrFail();

    expect($auditLog->metadata)->toMatchArray([
        'identifier_type' => 'email',
        'failure_reason' => 'banned',
    ]);
});

it('lets an already signed-in banned user reach the restricted notice and log out', function (): void {
    $user = User::factory()->create([
        'is_banned' => true,
        'ban_reason' => 'Safety review',
    ]);

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertRedirect(route('banned'));

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('keeps unverified users out of application pages after login', function (): void {
    $user = User::factory()->unverified()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertRedirect(route('verification.notice', absolute: false));

    $this->assertAuthenticatedAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_success',
    ]);
});

it('normalizes email and username identifiers before authentication and throttling', function (): void {
    $emailUser = User::factory()->create([
        'email' => 'trim-login@example.com',
    ]);
    $usernameUser = User::factory()->create([
        'username' => 'case_login',
    ]);

    $this->post('/login', [
        'email' => '  TRIM-LOGIN@EXAMPLE.COM  ',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($emailUser);
    auth()->logout();

    $this->post('/login', [
        'email' => 'Case_Login',
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($usernameUser);
});

it('rejects soft deleted users with valid credentials and records the deleted account attempt', function (): void {
    $user = User::factory()->create([
        'email' => 'deleted-login@example.com',
    ]);

    $user->delete();

    $this->post('/login', [
        'email' => 'deleted-login@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();

    $auditLog = AuthAuditLog::query()
        ->where('user_id', $user->id)
        ->where('event_type', 'login_failure')
        ->firstOrFail();

    expect($auditLog->metadata)->toMatchArray([
        'failure_reason' => 'deleted',
    ]);
});

it('restricts pending deletion accounts to secure cancellation before app access', function (): void {
    $user = User::factory()->create([
        'email' => 'pending-deletion@example.com',
        'scheduled_deletion_at' => now()->addDays(20),
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('account.deletion-pending', absolute: false));

    $this->assertAuthenticatedAs($user);

    $this->get(route('account.deletion-pending'))
        ->assertOk()
        ->assertSee('data-ui="account-deletion-pending-panel"', false);

    $this->get(route('feed.index'))
        ->assertRedirect(route('account.deletion-pending'));

    $this->post(route('account.cancel-deletion'), [
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['password']);

    expect($user->refresh()->scheduled_deletion_at)->not->toBeNull();

    $this->post(route('account.cancel-deletion'), [
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    expect($user->refresh()->scheduled_deletion_at)->toBeNull();

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'account_deletion_cancelled',
    ]);
});

it('restricts deactivated users to a password-confirmed reactivation screen', function (): void {
    $user = User::factory()->create([
        'email' => 'deactivated-login@example.com',
        'deactivated_at' => now()->subDay(),
        'deactivation_reason' => 'User requested pause',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('account.reactivation', absolute: false));

    $this->assertAuthenticatedAs($user);

    $this->get(route('account.reactivation'))
        ->assertOk()
        ->assertSee('data-ui="account-reactivation-panel"', false);

    $this->get(route('feed.index'))
        ->assertRedirect(route('account.reactivation'));

    $this->post(route('account.reactivate'), [
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    expect($user->refresh()->deactivated_at)->toBeNull();
});

it('restricts suspended users away from normal application pages', function (): void {
    $user = User::factory()->create([
        'email' => 'suspended-login@example.com',
        'suspended_until' => now()->addDay(),
        'suspension_reason' => 'Policy review',
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('account.suspended', absolute: false));

    $this->assertAuthenticatedAs($user);

    $this->get(route('account.suspended'))
        ->assertOk()
        ->assertSee('Account temporarily suspended');

    $this->get(route('feed.index'))
        ->assertRedirect(route('account.suspended'));
});

it('drops unsafe external intended URLs after successful login', function (): void {
    $user = User::factory()->create();

    $this->withSession(['url.intended' => 'https://evil.example/pets'])
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

it('rate limits repeated failed login attempts', function (): void {
    $user = User::factory()->create([
        'email' => 'limited-login@example.com',
    ]);
    $throttleKey = Str::transliterate('login|'.Str::lower($user->email).'|127.0.0.1');

    RateLimiter::clear($throttleKey);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    expect(RateLimiter::tooManyAttempts($throttleKey, 5))->toBeTrue();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email']);

    $this->assertGuest();
});

it('logout invalidates sensitive session state and records an audit event', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([
            'auth.password_confirmed_at' => now()->timestamp,
            'login.intended' => route('settings.index', absolute: false),
        ])
        ->post('/logout')
        ->assertRedirect('/')
        ->assertSessionMissing('auth.password_confirmed_at')
        ->assertSessionMissing('login.intended');

    $this->assertGuest();
    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'logout',
    ]);
});

it('redirects unverified users away from application pages', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertRedirect(route('verification.notice'));
});
