<?php

use App\Actions\Auth\RequestPasswordResetLinkAction;
use App\Actions\Auth\ResetPasswordAction;
use App\Enums\AccountStatus;
use App\Mail\Auth\PasswordChangedSecurityAlertMail;
use App\Mail\Auth\PasswordResetLinkMail;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Models\Security\AccountSecurityAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

it('renders the standalone password reset request page as Livewire', function (): void {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('data-ui="forgot-password-page"', false)
        ->assertSee('data-ui="password-email-form"', false)
        ->assertSee('Reset access to your account');
});

it('queues a password reset mailable for existing emails and stores a deterministic token hash', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'reset-link@example.com',
    ]);

    Livewire::test('pages.auth.forgot-password')
        ->set('resetEmail', 'RESET-LINK@EXAMPLE.COM')
        ->call('sendPasswordResetLink')
        ->assertSet('resetEmail', 'reset-link@example.com')
        ->assertSet('resetStatusMessage', RequestPasswordResetLinkAction::RESPONSE_MESSAGE)
        ->assertDispatched('password-reset-link-sent');

    Mail::assertQueued(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->user->is($user)
            && str_contains($mail->resetUrl, '/reset-password/');
    });

    $tokenRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();

    expect($tokenRecord?->token_hash)->toBeString()->toHaveLength(64);
});

it('keeps password reset requests generic when mail delivery fails', function (): void {
    Mail::shouldReceive('to')
        ->once()
        ->andThrow(new TransportException('SMTP auth failed'));

    $user = User::factory()->create([
        'email' => 'reset-mail-failure@example.com',
    ]);

    Livewire::test('pages.auth.forgot-password')
        ->set('resetEmail', 'RESET-MAIL-FAILURE@EXAMPLE.COM')
        ->call('sendPasswordResetLink')
        ->assertSet('resetEmail', 'reset-mail-failure@example.com')
        ->assertSet('resetStatusMessage', RequestPasswordResetLinkAction::RESPONSE_MESSAGE)
        ->assertDispatched('password-reset-link-sent');

    $tokenRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();

    expect($tokenRecord?->token_hash)->toBeString()->toHaveLength(64);

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->getKey(),
        'event_type' => 'password_reset_requested',
    ]);
});

it('uses the same password reset response for missing emails without sending mail', function (): void {
    Mail::fake();

    Livewire::test('pages.auth.forgot-password')
        ->set('resetEmail', 'missing-reset@example.com')
        ->call('sendPasswordResetLink')
        ->assertSet('resetStatusMessage', RequestPasswordResetLinkAction::RESPONSE_MESSAGE)
        ->assertDispatched('password-reset-link-sent');

    Mail::assertNothingQueued();

    $this->assertDatabaseHas('auth_audit_logs', [
        'event_type' => 'password_reset_requested',
        'user_id' => null,
    ]);
});

it('rate limits password reset requests per normalized email before account lookup', function (): void {
    Mail::fake();

    $email = 'reset-rate@example.com';
    $action = app(RequestPasswordResetLinkAction::class);
    $key = $action->rateLimitKey($email);

    RateLimiter::clear($key);
    RateLimiter::hit($key, 3600);
    RateLimiter::hit($key, 3600);
    RateLimiter::hit($key, 3600);

    User::factory()->create([
        'email' => $email,
    ]);

    Livewire::test('pages.auth.forgot-password')
        ->set('resetEmail', strtoupper($email))
        ->call('sendPasswordResetLink')
        ->assertSet('resetStatusMessage', RequestPasswordResetLinkAction::RESPONSE_MESSAGE);

    Mail::assertNothingQueued();
    expect(DB::table('password_reset_tokens')->where('email', $email)->exists())->toBeFalse();
});

it('renders the password reset confirmation page for a valid token', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'render-reset@example.com',
    ]);

    $token = requestPasswordResetTokenFor($user);

    $this->get(route('password.reset', ['token' => $token]))
        ->assertOk()
        ->assertSee('data-ui="reset-password-page"', false)
        ->assertSee('data-ui="password-reset-form"', false)
        ->assertSee('Create a new password')
        ->assertSee('readonly', false)
        ->assertSee(e($user->email), false);
});

it('redirects invalid and expired reset tokens back to the request page', function (): void {
    Mail::fake();

    $this->get(route('password.reset', ['token' => 'missing-token']))
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', ResetPasswordAction::INVALID_LINK_MESSAGE);

    $user = User::factory()->create([
        'email' => 'expired-reset@example.com',
    ]);

    $token = requestPasswordResetTokenFor($user);

    DB::table('password_reset_tokens')
        ->where('email', $user->email)
        ->update(['created_at' => now()->subMinutes(61)]);

    $this->get(route('password.reset', ['token' => $token]))
        ->assertRedirect(route('password.request'))
        ->assertSessionHas('status', ResetPasswordAction::EXPIRED_LINK_MESSAGE);
});

it('resets the password, invalidates sessions, queues a security alert, and signs the user in', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'complete-reset@example.com',
        'remember_token' => 'old-remember-token',
    ]);

    DB::table('sessions')->insert([
        [
            'id' => 'old-session-one',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Chrome on macOS',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'old-session-two',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Firefox on Linux',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $token = requestPasswordResetTokenFor($user);

    Livewire::test('pages.auth.reset-password', ['token' => $token])
        ->set('password', 'PetSocial2027!')
        ->set('password_confirmation', 'PetSocial2027!')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('feed.index'));

    $user->refresh();

    expect(Hash::check('PetSocial2027!', $user->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull()
        ->and($user->remember_token)->toBeNull()
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(AccountSecurityAction::query()->where('user_id', $user->id)->exists())->toBeTrue();

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->getKey(),
        'event_type' => 'password_reset',
    ]);

    Mail::assertQueued(PasswordChangedSecurityAlertMail::class, function (PasswordChangedSecurityAlertMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->user->is($user)
            && str_contains($mail->emergencyUrl, '/account/security-lock/');
    });
});

it('locks the account exactly once from the password change security alert link', function (): void {
    $user = User::factory()->create([
        'account_status' => AccountStatus::Active,
        'remember_token' => 'persistent-token',
    ]);
    $plainToken = 'emergency-lock-token';

    DB::table('sessions')->insert([
        'id' => 'session-to-lock',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Chrome on macOS',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $action = AccountSecurityAction::factory()
        ->for($user)
        ->create([
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => null,
        ]);

    $url = URL::signedRoute('password.security-lock', [
        'action' => $action->getKey(),
        'token' => $plainToken,
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Your account has been locked');

    $user->refresh();

    expect($user->account_status)->toBe(AccountStatus::Suspended)
        ->and($user->remember_token)->toBeNull()
        ->and($action->refresh()->used_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    $this->assertDatabaseHas('reports', [
        'reporter_user_id' => $user->getKey(),
        'reportable_type' => $user->getMorphClass(),
        'reportable_id' => $user->getKey(),
        'reason' => Report::REASON_PASSWORD_RESET_EMERGENCY_LOCK,
        'priority' => Report::PRIORITY_HIGH,
        'status' => Report::STATUS_PENDING,
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee('This security action was already taken');
});

it('renders password reset mailables with action links and fallback URLs', function (): void {
    $user = User::factory()->create(['name' => 'Mira']);
    $resetUrl = route('password.reset', ['token' => 'raw-token']);
    $emergencyUrl = URL::signedRoute('password.security-lock', [
        'action' => 1,
        'token' => 'emergency-token',
    ]);

    $resetMail = new PasswordResetLinkMail($user, $resetUrl);
    $alertMail = new PasswordChangedSecurityAlertMail($user, $emergencyUrl, now());

    $resetMail->assertHasSubject('Reset your PetSocial password');
    $resetMail->assertSeeInHtml('Reset my password');
    $resetMail->assertSeeInHtml($resetUrl);
    $resetMail->assertSeeInText($resetUrl);

    $alertMail->assertHasSubject('Your PetSocial password was changed');
    $alertMail->assertSeeInHtml('This was not me');
    $alertMail->assertSeeInHtml($emergencyUrl);
    $alertMail->assertSeeInText($emergencyUrl);
});

function requestPasswordResetTokenFor(User $user): string
{
    Livewire::test('pages.auth.forgot-password')
        ->set('resetEmail', $user->email)
        ->call('sendPasswordResetLink');

    $url = null;

    Mail::assertQueued(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail) use ($user, &$url): bool {
        if (! $mail->user->is($user)) {
            return false;
        }

        $url = $mail->resetUrl;

        return true;
    });

    expect($url)->toBeString();

    return basename((string) parse_url((string) $url, PHP_URL_PATH));
}
