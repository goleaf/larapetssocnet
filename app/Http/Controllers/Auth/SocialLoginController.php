<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\SocialLoginService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SocialLoginController extends Controller
{
    public function redirect(string $provider, Request $request, SocialLoginService $socialLogins): RedirectResponse
    {
        $config = $socialLogins->providerConfig($provider);
        $state = Str::random(40);

        $request->session()->put("oauth.{$provider}.state", $state);

        $query = http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => route('social.callback', ['provider' => $provider]),
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return redirect()->away($config['redirect_url'].'?'.$query);
    }

    public function callback(string $provider, Request $request, SocialLoginService $socialLogins, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $expectedState = $request->session()->pull("oauth.{$provider}.state");

        if (! is_string($expectedState) || ! hash_equals($expectedState, (string) $request->query('state'))) {
            throw ValidationException::withMessages([
                'provider' => 'The social login session expired. Please try again.',
            ]);
        }

        $profile = $socialLogins->fetchProviderUser($provider, (string) $request->query('code'));
        $user = $socialLogins->loginOrCreateUser($provider, $profile, $request);

        if ((bool) $user->is_banned || $user->trashed()) {
            $auditLogger->record($user, 'social_login_rejected', $request, [
                'provider' => $provider,
                'restriction_reason' => (bool) $user->is_banned ? 'banned' : 'deleted',
            ]);

            return redirect()->route('login')->withErrors([
                'email' => trans('auth.failed'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $restrictedRoute = $this->restrictedRouteFor($user);

        if ($restrictedRoute !== null) {
            $auditLogger->record($user, 'social_login_restricted', $request, [
                'provider' => $provider,
                'restriction_route' => $restrictedRoute,
            ]);

            return redirect()->route($restrictedRoute);
        }

        if ($user->two_factor_secret !== null) {
            $request->session()->put('auth.two_factor_pending_user_id', $user->getKey());
        } else {
            $request->session()->forget('auth.two_factor_pending_user_id');
            $user->forceFill([
                'last_login_at' => now(),
                'last_seen_at' => now(),
            ])->save();
        }

        $auditLogger->record($user, 'social_login_success', $request, [
            'provider' => $provider,
            'provider_id_hash' => hash('sha256', $profile['provider_id']),
            'email_matched' => $profile['email'] !== null,
        ]);

        if ($user->two_factor_secret !== null) {
            return redirect()->route('two-factor.challenge');
        }

        return $user->hasVerifiedEmail()
            ? redirect()->route('dashboard')
            : redirect()->route('verification.notice');
    }

    private function restrictedRouteFor(User $user): ?string
    {
        if ($user->scheduled_deletion_at !== null) {
            return 'account.deletion-pending';
        }

        if ($user->deactivated_at !== null) {
            return 'account.reactivation';
        }

        $suspendedUntil = $user->getAttribute('suspended_until');

        if ($suspendedUntil instanceof CarbonInterface && $suspendedUntil->isFuture()) {
            return 'account.suspended';
        }

        return null;
    }
}
