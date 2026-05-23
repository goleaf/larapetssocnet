<?php

namespace App\Services\Auth;

use App\Models\Identity\SocialAccount;
use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialLoginService
{
    /**
     * @return array{provider_id: string, email: ?string, email_verified: bool, name: ?string, nickname: ?string, avatar: ?string, token: ?string, refresh_token: ?string, expires_at: mixed, raw: array<string, mixed>}
     */
    public function fetchProviderUser(string $provider, string $code): array
    {
        $config = $this->providerConfig($provider);

        $tokenResponse = Http::asForm()->post($config['token_url'], [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => route('social.callback', ['provider' => $provider]),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ])->throw()->json();

        $accessToken = is_array($tokenResponse) ? (string) ($tokenResponse['access_token'] ?? '') : '';

        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'provider' => 'The social login provider did not return an access token.',
            ]);
        }

        $profile = Http::withToken($accessToken)->get($config['user_url'])->throw()->json();

        if (! is_array($profile)) {
            throw ValidationException::withMessages([
                'provider' => 'The social login provider did not return a valid profile.',
            ]);
        }

        return $this->normalizeProviderProfile($provider, $profile, $tokenResponse);
    }

    /**
     * @param  array{provider_id: string, email: ?string, email_verified: bool, name: ?string, nickname: ?string, avatar: ?string, token: ?string, refresh_token: ?string, expires_at: mixed, raw: array<string, mixed>}  $profile
     */
    public function loginOrCreateUser(string $provider, array $profile, Request $request): User
    {
        return DB::transaction(function () use ($provider, $profile, $request): User {
            $socialAccount = SocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $profile['provider_id'])
                ->first();

            if ($socialAccount instanceof SocialAccount) {
                $this->updateSocialAccount($socialAccount, $profile);

                return $socialAccount->user()->firstOrFail();
            }

            $user = $this->matchingUser($profile) ?? $this->createUser($profile, $request);

            $this->createSocialAccount($user, $provider, $profile);

            return $user;
        });
    }

    /**
     * @return array{client_id: string, client_secret: string, redirect_url: string, token_url: string, user_url: string, scopes: list<string>}
     */
    public function providerConfig(string $provider): array
    {
        $providerConfig = config("services.{$provider}");

        if (! in_array($provider, ['google', 'facebook'], true) || ! is_array($providerConfig)) {
            throw ValidationException::withMessages(['provider' => 'Unsupported social login provider.']);
        }

        foreach (['client_id', 'client_secret', 'redirect_url', 'token_url', 'user_url'] as $key) {
            if (! is_string($providerConfig[$key] ?? null) || $providerConfig[$key] === '') {
                throw ValidationException::withMessages(['provider' => 'Social login is not configured for this provider.']);
            }
        }

        return [
            'client_id' => $providerConfig['client_id'],
            'client_secret' => $providerConfig['client_secret'],
            'redirect_url' => $providerConfig['redirect_url'],
            'token_url' => $providerConfig['token_url'],
            'user_url' => $providerConfig['user_url'],
            'scopes' => array_values(array_filter((array) ($providerConfig['scopes'] ?? []), 'is_string')),
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $tokenResponse
     * @return array{provider_id: string, email: ?string, email_verified: bool, name: ?string, nickname: ?string, avatar: ?string, token: ?string, refresh_token: ?string, expires_at: mixed, raw: array<string, mixed>}
     */
    private function normalizeProviderProfile(string $provider, array $profile, array $tokenResponse): array
    {
        $providerId = (string) ($profile['id'] ?? $profile['sub'] ?? '');

        if ($providerId === '') {
            throw ValidationException::withMessages([
                'provider' => 'The social login provider did not return an account ID.',
            ]);
        }

        $email = is_string($profile['email'] ?? null) ? Str::lower($profile['email']) : null;
        $verified = (bool) ($profile['email_verified'] ?? $profile['verified'] ?? false);
        $expiresIn = isset($tokenResponse['expires_in']) && is_numeric($tokenResponse['expires_in'])
            ? now()->addSeconds((int) $tokenResponse['expires_in'])
            : null;

        return [
            'provider_id' => $providerId,
            'email' => $email,
            'email_verified' => $verified,
            'name' => is_string($profile['name'] ?? null) ? $profile['name'] : null,
            'nickname' => is_string($profile['login'] ?? $profile['username'] ?? null) ? ($profile['login'] ?? $profile['username']) : null,
            'avatar' => is_string($profile['picture'] ?? $profile['avatar_url'] ?? null) ? ($profile['picture'] ?? $profile['avatar_url']) : null,
            'token' => is_string($tokenResponse['access_token'] ?? null) ? $tokenResponse['access_token'] : null,
            'refresh_token' => is_string($tokenResponse['refresh_token'] ?? null) ? $tokenResponse['refresh_token'] : null,
            'expires_at' => $expiresIn,
            'raw' => $profile,
        ];
    }

    /**
     * @param  array{provider_id: string, email: ?string, email_verified: bool, name: ?string, nickname: ?string, avatar: ?string, token: ?string, refresh_token: ?string, expires_at: mixed, raw: array<string, mixed>}  $profile
     */
    private function matchingUser(array $profile): ?User
    {
        if (! $profile['email_verified'] || $profile['email'] === null) {
            return null;
        }

        return User::query()->where('email', $profile['email'])->first();
    }

    /**
     * @param  array{provider_id: string, email: ?string, email_verified: bool, name: ?string, nickname: ?string, avatar: ?string, token: ?string, refresh_token: ?string, expires_at: mixed, raw: array<string, mixed>}  $profile
     */
    private function createUser(array $profile, Request $request): User
    {
        $email = $profile['email'];

        if ($email === null) {
            throw ValidationException::withMessages([
                'provider' => 'The social login provider did not return an email address.',
            ]);
        }

        $displayName = trim((string) ($profile['name'] ?: Str::before($email, '@')));

        $user = User::query()->create(array_merge(
            User::defaultRegistrationPrivacySettings(),
            [
                'name' => $displayName,
                'display_name' => $displayName,
                'username' => User::generateUniqueUsername((string) ($profile['nickname'] ?: Str::before($email, '@'))),
                'email' => $email,
                'password' => Hash::make(Str::password(32)),
                'notification_preferences' => User::defaultNotificationPreferences(),
                'terms_accepted_at' => now(),
                'terms_version' => User::CURRENT_TERMS_VERSION,
                'registration_ip_address' => $request->ip(),
                'registration_user_agent' => $request->userAgent(),
                'role' => 'member',
                'onboarding_step' => '1',
            ],
        ));

        if ($profile['email_verified']) {
            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();
        }

        return $user;
    }

    /**
     * @param  array{provider_id: string, email: ?string, email_verified: bool, name: ?string, nickname: ?string, avatar: ?string, token: ?string, refresh_token: ?string, expires_at: mixed, raw: array<string, mixed>}  $profile
     */
    private function createSocialAccount(User $user, string $provider, array $profile): void
    {
        SocialAccount::query()->create([
            'user_id' => $user->getKey(),
            'provider' => $provider,
            'provider_id' => $profile['provider_id'],
            'provider_email' => $profile['email'],
            'provider_nickname' => $profile['nickname'],
            'provider_name' => $profile['name'],
            'avatar_url' => $profile['avatar'],
            'token' => $profile['token'],
            'refresh_token' => $profile['refresh_token'],
            'expires_at' => $profile['expires_at'],
            'provider_payload' => $profile['raw'],
        ]);
    }

    /**
     * @param  array{provider_id: string, email: ?string, email_verified: bool, name: ?string, nickname: ?string, avatar: ?string, token: ?string, refresh_token: ?string, expires_at: mixed, raw: array<string, mixed>}  $profile
     */
    private function updateSocialAccount(SocialAccount $socialAccount, array $profile): void
    {
        $socialAccount->update([
            'provider_email' => $profile['email'],
            'provider_nickname' => $profile['nickname'],
            'provider_name' => $profile['name'],
            'avatar_url' => $profile['avatar'],
            'token' => $profile['token'],
            'refresh_token' => $profile['refresh_token'],
            'expires_at' => $profile['expires_at'],
            'provider_payload' => $profile['raw'],
        ]);
    }
}
