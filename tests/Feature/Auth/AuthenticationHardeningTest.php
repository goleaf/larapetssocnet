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
        ->assertSessionHasErrors(['email' => trans('auth.failed')]);

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

it('keeps unverified users out of application pages after login', function (): void {
    $user = User::factory()->unverified()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_success',
    ]);
});

it('rate limits repeated failed login attempts', function (): void {
    $user = User::factory()->create([
        'email' => 'limited-login@example.com',
    ]);
    $throttleKey = Str::transliterate(Str::lower($user->email).'|127.0.0.1');

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
