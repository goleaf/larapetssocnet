<?php

use App\Enums\AccountStatus;
use App\Models\Identity\User;
use App\Models\Security\AuthAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('requires age confirmation terms and a non-common password at registration', function (): void {
    $underageDate = now()->subYears(10);

    Livewire::test('pages.auth.register')
        ->set('name', 'Young User')
        ->set('username', 'young-user')
        ->set('email', 'young.user@gmail.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('birth_day', (string) $underageDate->day)
        ->set('birth_month', (string) $underageDate->month)
        ->set('birth_year', (string) $underageDate->year)
        ->call('register')
        ->assertHasErrors(['password', 'birth_date', 'terms']);

    expect(User::query()->where('email', 'young.user@gmail.com')->exists())->toBeFalse();
});

it('silently rejects honeypot registrations without creating an account', function (): void {
    Livewire::test('pages.auth.register')
        ->set('middleName', 'Acme Bot')
        ->call('register')
        ->assertRedirect(route('verification.notice'));

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('auth_audit_logs', 0);
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

    $failureLog = AuthAuditLog::query()
        ->where('user_id', $user->id)
        ->where('event_type', 'login_failure')
        ->firstOrFail();

    expect($failureLog->identifier_hash)->toBe(hash('sha256', 'audit_user'))
        ->and($user->refresh()->failed_login_attempts)->toBe(1)
        ->and($user->last_failed_login_at)->not->toBeNull();

    $this->post('/login', [
        'email' => 'audit_user',
        'password' => 'password',
    ])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect($user->refresh()->failed_login_attempts)->toBe(0)
        ->and($user->last_failed_login_at)->toBeNull();

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

it('progressively locks known accounts after repeated failed login attempts', function (): void {
    $user = User::factory()->create([
        'email' => 'limited-login@example.com',
    ]);

    for ($attempt = 0; $attempt < 4; $attempt++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['email' => trans('auth.failed')]);
    }

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors([
        'email' => 'Too many failed login attempts. Please wait 1 minute before trying again.',
    ]);

    expect($user->refresh()->failed_login_attempts)->toBe(5)
        ->and($user->last_failed_login_at)->not->toBeNull();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors([
        'email' => 'Too many failed login attempts. Please wait 1 minute before trying again.',
    ]);

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

it('keeps enum-suspended authenticated users away from application pages', function (): void {
    $user = User::factory()->create([
        'account_status' => AccountStatus::Suspended,
        'suspended_until' => null,
    ]);

    $this->actingAs($user)
        ->get(route('feed.index'))
        ->assertRedirect(route('account.suspended'));
});
