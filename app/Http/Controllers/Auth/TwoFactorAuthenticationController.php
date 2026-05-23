<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Requests\Auth\TwoFactorEnableRequest;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\TwoFactorAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class TwoFactorAuthenticationController extends Controller
{
    public function show(Request $request): RedirectResponse|View
    {
        if ($request->user()?->two_factor_secret === null) {
            return redirect()->route('dashboard');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(TwoFactorChallengeRequest $request, TwoFactorAuthenticator $authenticator, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->two_factor_secret === null) {
            return redirect()->route('login');
        }

        $validCode = filled($request->input('code'))
            && $authenticator->verifyCode((string) $user->two_factor_secret, (string) $request->input('code'));

        $validRecoveryCode = filled($request->input('recovery_code'))
            && $authenticator->consumeRecoveryCode($user, (string) $request->input('recovery_code'));

        if (! $validCode && ! $validRecoveryCode) {
            $auditLogger->record($user, 'two_factor_challenge_failed', $request);

            return back()->withErrors([
                'code' => 'The authentication code is invalid or has expired.',
            ]);
        }

        $request->session()->forget('auth.two_factor_pending_user_id');
        $request->session()->put('auth.two_factor_confirmed_at', now()->timestamp);

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $auditLogger->record($user, 'two_factor_challenge_passed', $request, [
            'method' => $validRecoveryCode ? 'recovery_code' : 'totp',
        ]);

        return $user->hasVerifiedEmail()
            ? redirect()->intended(route('dashboard'))
            : redirect()->route('verification.notice');
    }

    public function create(Request $request, TwoFactorAuthenticator $authenticator): View
    {
        $user = $request->user();
        $secret = (string) ($user->two_factor_secret ?: $request->session()->get('auth.two_factor_preview_secret'));

        if ($secret === '') {
            $secret = $authenticator->generateSecret();
            $request->session()->put('auth.two_factor_preview_secret', $secret);
        }

        $uri = $authenticator->otpauthUri($user, $secret);

        return view('auth.two-factor-settings', [
            'user' => $user,
            'secret' => $secret,
            'qrCode' => new HtmlString($authenticator->qrSvgPayload($uri)),
            'enabled' => $user->two_factor_secret !== null,
        ]);
    }

    public function enable(TwoFactorEnableRequest $request, TwoFactorAuthenticator $authenticator, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $secret = (string) ($request->session()->pull('auth.two_factor_preview_secret') ?: $authenticator->generateSecret());
        $recoveryCodes = $authenticator->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $authenticator->hashRecoveryCodes($recoveryCodes),
        ])->save();

        $auditLogger->record($user, 'two_factor_enabled', $request);

        return redirect()
            ->route('settings.two-factor')
            ->with('success', 'Two-factor authentication has been enabled.')
            ->with('recovery_codes', $recoveryCodes);
    }

    public function disable(TwoFactorEnableRequest $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $request->session()->forget('auth.two_factor_pending_user_id');
        $auditLogger->record($user, 'two_factor_disabled', $request);

        return redirect()
            ->route('settings.two-factor')
            ->with('success', 'Two-factor authentication has been disabled.');
    }
}
