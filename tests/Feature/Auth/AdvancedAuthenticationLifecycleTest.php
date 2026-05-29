<?php

use App\Mail\Auth\MagicLoginLinkMail;
use App\Mail\Auth\PasswordChangedSecurityAlertMail;
use App\Models\Identity\SocialAccount;
use App\Models\Identity\User;
use App\Models\Security\MagicLoginToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('does not reveal whether a password reset email belongs to an account', function (): void {
    Mail::fake();

    $this->post(route('password.email'), [
        'email' => 'missing@example.com',
    ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'If an account with that email exists, you will receive a password reset link shortly.');

    Mail::assertNothingQueued();
    $this->assertDatabaseHas('auth_audit_logs', [
        'event_type' => 'password_reset_requested',
        'user_id' => null,
    ]);
});

it('invalidates existing database sessions after a successful password reset', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'reset-session@example.com',
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

    $token = Password::broker()->createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'PetSocial2027!',
        'password_confirmation' => 'PetSocial2027!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('PetSocial2027!', $user->refresh()->password))->toBeTrue()
        ->and($user->password_changed_at)->not->toBeNull()
        ->and($user->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    $this->assertGuest();
    Mail::assertQueued(PasswordChangedSecurityAlertMail::class);
});

it('creates and consumes magic login links exactly once', function (): void {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'magic@example.com',
    ]);

    $this->post(route('magic-login.store'), [
        'email' => 'magic@example.com',
    ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'If an account with that email exists, you will receive a login link shortly.');

    $magicToken = MagicLoginToken::query()->firstOrFail();
    $url = null;

    Mail::assertQueued(MagicLoginLinkMail::class, function (MagicLoginLinkMail $mail) use ($user, &$url): bool {
        $url = $mail->loginUrl;

        return $mail->hasTo($user->email)
            && $mail->user->is($user)
            && str_contains($url, '/magic-login/')
            && ! str_contains($url, 'token_hash');
    });

    expect($magicToken->token_hash)->toHaveLength(64);

    $this->get((string) $url)
        ->assertRedirect(route('feed.index'));

    $this->assertAuthenticatedAs($user);
    expect($magicToken->refresh()->used_at)->not->toBeNull();

    auth()->logout();

    $this->get((string) $url)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'This login link has already been used. Request a new login link to continue.',
        ]);
});

it('rejects expired magic login tokens', function (): void {
    $user = User::factory()->create();
    $plainToken = 'expired-magic-token';
    MagicLoginToken::factory()
        ->for($user)
        ->expired()
        ->create([
            'token_hash' => hash('sha256', $plainToken),
        ]);

    $url = route('magic-login.consume', ['token' => $plainToken]);

    $this->get($url)
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'This login link has expired. Request a new login link to continue.',
        ]);

    $this->assertGuest();
});

it('rejects invalid magic login tokens', function (): void {
    $this->get(route('magic-login.consume', ['token' => 'missing-magic-token']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors([
            'email' => 'This login link is invalid. Request a new login link to continue.',
        ]);

    $this->assertGuest();
});

it('creates a new account from a verified social login profile', function (): void {
    configureGoogleSocialLogin();
    fakeGoogleProvider('provider-new', 'social-new@example.com');

    $state = socialLoginState($this, 'google');

    $this->get(route('social.callback', ['provider' => 'google', 'code' => 'valid-code', 'state' => $state]))
        ->assertRedirect(route('onboarding.show'));

    $user = User::query()->where('email', 'social-new@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeTrue();
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'provider-new',
        'provider_avatar_url' => 'https://example.com/avatar.jpg',
    ]);
});

it('logs in a returning social account without creating a duplicate user', function (): void {
    configureGoogleSocialLogin();

    $user = User::factory()->create([
        'email' => 'returning@example.com',
    ]);

    SocialAccount::factory()
        ->for($user)
        ->create([
            'provider' => 'google',
            'provider_user_id' => 'provider-returning',
        ]);

    fakeGoogleProvider('provider-returning', 'changed-returning@example.com');
    $state = socialLoginState($this, 'google');

    $this->get(route('social.callback', ['provider' => 'google', 'code' => 'valid-code', 'state' => $state]))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    expect(User::query()->count())->toBe(1)
        ->and(SocialAccount::query()->count())->toBe(1);
});

it('merges a verified social login profile with an existing email account', function (): void {
    configureGoogleSocialLogin();

    $user = User::factory()->create([
        'email' => 'merge@example.com',
    ]);

    fakeGoogleProvider('provider-merge', 'merge@example.com');
    $state = socialLoginState($this, 'google');

    $this->get(route('social.callback', ['provider' => 'google', 'code' => 'valid-code', 'state' => $state]))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'provider-merge',
    ]);
});

it('shows only the current users device sessions and can delete other sessions', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    DB::table('sessions')->insert([
        [
            'id' => 'visible-session',
            'user_id' => $user->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Version/17.0 Safari/605.1.15',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'hidden-session',
            'user_id' => $otherUser->id,
            'ip_address' => '10.0.0.99',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('settings.password'))
        ->assertOk()
        ->assertSee('Safari 17.0 on Mac')
        ->assertSee('10.0.0.1')
        ->assertDontSee('10.0.0.99');

    $this->actingAs($user)
        ->delete(route('settings.sessions.destroy-other'), [
            'password' => 'password',
        ])
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $otherUser->id)->exists())->toBeTrue();
});

function configureGoogleSocialLogin(): void
{
    config()->set('services.google', [
        'client_id' => 'google-client-id',
        'client_secret' => 'google-client-secret',
        'redirect_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'user_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
        'scopes' => ['openid', 'profile', 'email'],
    ]);
}

function fakeGoogleProvider(string $providerId, string $email): void
{
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'provider-access-token',
            'expires_in' => 3600,
        ]),
        'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
            'sub' => $providerId,
            'email' => $email,
            'email_verified' => true,
            'name' => 'Social Pet Owner',
            'picture' => 'https://example.com/avatar.jpg',
        ]),
    ]);
}

function socialLoginState(TestCase $test, string $provider): string
{
    $response = $test->get(route('social.redirect', ['provider' => $provider]));
    $response->assertRedirect();

    parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

    return (string) $query['state'];
}
