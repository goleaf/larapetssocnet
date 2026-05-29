<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $user = $this->verificationUser($request);

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if (! $request->user() instanceof User) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            $auditLogger->record($user, 'email_verified', $request);
        }

        return redirect()->intended($this->postVerificationPath($user));
    }

    private function verificationUser(Request $request): User
    {
        $routeUserId = (string) $request->route('id');
        $authenticatedUser = $request->user();

        if ($authenticatedUser instanceof User) {
            abort_unless((string) $authenticatedUser->getKey() === $routeUserId, 403);

            return $authenticatedUser;
        }

        $user = User::query()->find($routeUserId);

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function postVerificationPath(User $user): string
    {
        if ($user->hasCompletedOnboarding()) {
            return route('feed.index', absolute: false);
        }

        return route('onboarding.show', absolute: false).'?verified=1';
    }
}
