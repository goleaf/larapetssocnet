<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AttemptLoginAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request, AttemptLoginAction $attemptLogin): RedirectResponse
    {
        $restrictedResponse = $attemptLogin->handle($request);

        $user = $request->user();

        if ($user !== null) {
            $request->session()->regenerate();
        }

        if ($restrictedResponse instanceof RedirectResponse) {
            return $restrictedResponse;
        }

        if (
            $user !== null
            && $user->two_factor_secret !== null
            && (string) $request->session()->get('auth.two_factor_pending_user_id') === (string) $user->getKey()
        ) {
            return redirect()->route('two-factor.challenge');
        }

        if (! $user?->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        return $this->redirectToSafeIntendedUrl($request);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        if ($request->user() !== null) {
            $auditLogger->record($request->user(), 'logout', $request, [
                'logout_type' => 'manual',
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function redirectToSafeIntendedUrl(LoginRequest $request): RedirectResponse
    {
        $fallback = route('dashboard', absolute: false);
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return redirect($fallback);
        }

        if (Str::startsWith($intended, '/') && ! Str::startsWith($intended, '//')) {
            return redirect()->to($intended);
        }

        $host = parse_url($intended, PHP_URL_HOST);
        $scheme = parse_url($intended, PHP_URL_SCHEME);

        if ($host === $request->getHost() && in_array($scheme, ['http', 'https'], true)) {
            return redirect()->to($intended);
        }

        return redirect($fallback);
    }
}
