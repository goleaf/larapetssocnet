<?php

use App\Models\Identity\User;
use App\Services\Auth\TwoFactorAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('enables two-factor authentication with encrypted secrets and hashed recovery codes', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.two-factor'))
        ->assertOk()
        ->assertSee('data-ui="two-factor-qr-code"', false);

    $this->actingAs($user)
        ->post(route('settings.two-factor.enable'), [
            'current_password' => 'password',
        ])
        ->assertRedirect(route('settings.two-factor'))
        ->assertSessionHas('recovery_codes');

    $user->refresh();
    $recoveryCodes = session('recovery_codes');
    $raw = DB::table('users')->where('id', $user->id)->first();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_recovery_codes)->toHaveCount(8)
        ->and($raw->two_factor_secret)->not->toBe($user->two_factor_secret)
        ->and($raw->two_factor_recovery_codes)->not->toContain($recoveryCodes[0])
        ->and(collect($user->two_factor_recovery_codes)->contains(fn (string $hash): bool => Hash::check(normalizeRecoveryCodeForTest($recoveryCodes[0]), $hash)))->toBeTrue();
});

it('requires a two-factor challenge after password authentication before protected pages load', function (): void {
    $authenticator = app(TwoFactorAuthenticator::class);
    $secret = $authenticator->generateSecret();
    $user = User::factory()->create([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $authenticator->hashRecoveryCodes(['recovery-one']),
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.challenge'));

    $this->assertAuthenticatedAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('two-factor.challenge'));
});

it('accepts a current authenticator code and clears the pending challenge', function (): void {
    $authenticator = app(TwoFactorAuthenticator::class);
    $secret = $authenticator->generateSecret();
    $user = User::factory()->create([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $authenticator->hashRecoveryCodes(['recovery-one']),
    ]);

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('two-factor.challenge'));

    $this->post(route('two-factor.challenge.store'), [
        'code' => $authenticator->codeAt($secret),
    ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing('auth.two_factor_pending_user_id');

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'two_factor_challenge_passed',
    ]);
});

it('rejects expired authenticator codes', function (): void {
    $authenticator = app(TwoFactorAuthenticator::class);
    $secret = $authenticator->generateSecret();
    $user = User::factory()->create([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $authenticator->hashRecoveryCodes(['recovery-one']),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.two_factor_pending_user_id' => $user->id])
        ->post(route('two-factor.challenge.store'), [
            'code' => $authenticator->codeAt($secret, time() - 90),
        ])
        ->assertSessionHasErrors(['code']);

    $this->assertDatabaseHas('auth_audit_logs', [
        'user_id' => $user->id,
        'event_type' => 'two_factor_challenge_failed',
    ]);
});

it('accepts a recovery code once and rejects reuse', function (): void {
    $authenticator = app(TwoFactorAuthenticator::class);
    $secret = $authenticator->generateSecret();
    $recoveryCode = 'Recover1234';
    $user = User::factory()->create([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => $authenticator->hashRecoveryCodes([$recoveryCode]),
    ]);

    $this->actingAs($user)
        ->withSession(['auth.two_factor_pending_user_id' => $user->id])
        ->post(route('two-factor.challenge.store'), [
            'recovery_code' => $recoveryCode,
        ])
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->two_factor_recovery_codes)->toBe([]);

    $this->actingAs($user)
        ->withSession(['auth.two_factor_pending_user_id' => $user->id])
        ->post(route('two-factor.challenge.store'), [
            'recovery_code' => $recoveryCode,
        ])
        ->assertSessionHasErrors(['code']);
});

function normalizeRecoveryCodeForTest(string $code): string
{
    return strtolower(str_replace([' ', '-'], '', trim($code)));
}
