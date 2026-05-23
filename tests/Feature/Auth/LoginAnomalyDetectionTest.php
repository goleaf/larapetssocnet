<?php

use App\Jobs\Auth\DetectLoginAnomaly;
use App\Mail\Auth\LoginAnomalySecurityAlertMail;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Models\Security\LoginSecurityAlert;
use App\Services\Auth\GeoIpLookupService;
use App\Services\Auth\UserAgentDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('dispatches anomaly detection after a successful credential login', function (): void {
    Queue::fake();

    $user = User::factory()->create([
        'email' => 'anomaly-login@example.com',
    ]);

    $this->post(route('login'), [
        'credential' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    Queue::assertPushed(DetectLoginAnomaly::class);
});

it('does not alert when the current login country was seen in the last ninety days', function (): void {
    Mail::fake();

    $user = User::factory()->create();
    $loginAt = now();

    DB::table('auth_audit_logs')->insert([
        'user_id' => $user->id,
        'event_type' => 'login_success',
        'ip_address' => '203.0.113.9',
        'user_agent' => anomalySafariUserAgent(),
        'country' => 'United States',
        'city' => 'Example City',
        'additional_data' => json_encode([
            'country_code' => 'US',
        ], JSON_THROW_ON_ERROR),
        'created_at' => $loginAt->copy()->subDay(),
    ]);

    (new DetectLoginAnomaly(
        userId: $user->id,
        ipAddress: '203.0.113.10',
        userAgent: anomalySafariUserAgent(),
        loginAt: $loginAt->toIso8601String(),
    ))->handle(app(GeoIpLookupService::class), app(UserAgentDetailsService::class));

    expect(LoginSecurityAlert::query()->count())->toBe(0);
    Mail::assertNothingQueued();
});

it('queues a security alert when the login country is new to recent history', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'new-country@example.com',
    ]);

    (new DetectLoginAnomaly(
        userId: $user->id,
        ipAddress: '198.51.100.24',
        userAgent: anomalySafariUserAgent(),
        loginAt: now()->toIso8601String(),
    ))->handle(app(GeoIpLookupService::class), app(UserAgentDetailsService::class));

    $alert = LoginSecurityAlert::query()->first();

    expect($alert)->toBeInstanceOf(LoginSecurityAlert::class)
        ->and($alert?->country_code)->toBe('CA')
        ->and($alert?->country)->toBe('Canada')
        ->and($alert?->browser_name)->toBe('Safari');

    Mail::assertQueued(LoginAnomalySecurityAlertMail::class, function (LoginAnomalySecurityAlertMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && str_contains($mail->dismissUrl, '/account/login-alert/')
            && str_contains($mail->secureUrl, '/account/login-alert/');
    });
});

it('dismisses login anomaly alerts without invalidating sessions', function (): void {
    $user = User::factory()->create();
    $token = 'plain-dismiss-token';
    $alert = LoginSecurityAlert::factory()
        ->for($user)
        ->create([
            'token_hash' => hash('sha256', $token),
        ]);

    DB::table('sessions')->insert([
        'id' => 'active-session',
        'user_id' => $user->id,
        'ip_address' => '198.51.100.24',
        'user_agent' => anomalySafariUserAgent(),
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);

    $this->get(URL::signedRoute('login-security-alert.dismiss', [
        'alert' => $alert,
        'token' => $token,
    ]))
        ->assertOk()
        ->assertSee('Thanks for confirming');

    expect($alert->refresh()->dismissed_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', 'active-session')->exists())->toBeTrue();

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_anomaly_dismissed',
    ]);
});

it('secures the account from a login anomaly alert by clearing sessions and creating moderation review', function (): void {
    $user = User::factory()->create([
        'remember_token' => 'persistent-token',
    ]);
    $otherUser = User::factory()->create();
    $token = 'plain-secure-token';
    $alert = LoginSecurityAlert::factory()
        ->for($user)
        ->create([
            'token_hash' => hash('sha256', $token),
            'country' => 'Canada',
            'country_code' => 'CA',
        ]);

    DB::table('sessions')->insert([
        [
            'id' => 'active-session',
            'user_id' => $user->id,
            'ip_address' => '198.51.100.24',
            'user_agent' => anomalySafariUserAgent(),
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'other-users-session',
            'user_id' => $otherUser->id,
            'ip_address' => '203.0.113.24',
            'user_agent' => anomalySafariUserAgent(),
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $url = URL::signedRoute('login-security-alert.secure', [
        'alert' => $alert,
        'token' => $token,
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee('Your account sessions were secured');

    expect($alert->refresh()->secured_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $otherUser->id)->exists())->toBeTrue()
        ->and($user->refresh()->remember_token)->toBeNull();

    $this->assertDatabaseHas('reports', [
        'reporter_user_id' => $user->id,
        'reason' => Report::REASON_LOGIN_ANOMALY_SECURITY_ALERT,
        'priority' => Report::PRIORITY_HIGH,
    ]);

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'login_anomaly_secured',
    ]);

    $this->get($url)
        ->assertOk()
        ->assertSee('already handled');
});

function anomalySafariUserAgent(): string
{
    return 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';
}
