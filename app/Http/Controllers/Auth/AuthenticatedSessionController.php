<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\AuthenticationResult;
use App\Http\Controllers\Controller;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, AuthenticateUserAction $authenticate): RedirectResponse
    {
        $validated = $request->validate([
            'credential' => ['nullable', 'string', 'max:255', 'required_without:email'],
            'email' => ['nullable', 'string', 'max:255', 'required_without:credential'],
            'password' => ['required', 'string', 'max:1024'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $field = array_key_exists('credential', $validated) ? 'credential' : 'email';
        $credential = (string) ($validated['credential'] ?? $validated['email'] ?? '');

        $result = $authenticate->handle(
            credential: $credential,
            password: (string) $validated['password'],
            remember: $request->boolean('remember'),
            request: $request,
        );

        if ($result->failed()) {
            throw ValidationException::withMessages([
                $field => $result->message,
            ]);
        }

        $request->session()->regenerate();

        return $this->redirectAfterAuthentication($request, $result);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $guard = Auth::guard('web');
        $rememberCookie = method_exists($guard, 'getRecallerName') ? $guard->getRecallerName() : null;

        if ($user !== null) {
            $auditLogger->record($user, 'logout', $request, [
                'logout_type' => 'manual',
            ]);

            $user->forceFill([
                'remember_token' => null,
            ])->saveQuietly();
        }

        $guard->logout();

        if (is_string($rememberCookie)) {
            Cookie::queue(Cookie::forget($rememberCookie));
        }

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function redirectAfterAuthentication(Request $request, AuthenticationResult $result): RedirectResponse
    {
        if ($result->redirectRoute !== null) {
            return redirect()->route($result->redirectRoute);
        }

        if ($result->requiresTwoFactor) {
            return redirect()->route('two-factor.challenge');
        }

        if (! $result->user?->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $result->user->hasCompletedOnboarding()) {
            return redirect()->route('onboarding.show');
        }

        return $this->redirectToSafeIntendedUrl($request);
    }

    private function redirectToSafeIntendedUrl(Request $request): RedirectResponse
    {
        $fallback = route('feed.index', absolute: false);
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
