<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\AuthMailDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request, AuthAuditLogger $auditLogger, AuthMailDispatcher $mailDispatcher): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('feed.index', absolute: false));
        }

        $key = 'verification-email-resend:user:'.$user->getKey();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('status', 'verification-link-rate-limited');
        }

        RateLimiter::hit($key, 3600);

        if (! $mailDispatcher->queueVerificationEmail($user)) {
            return back()->with('status', 'verification-link-failed');
        }

        $auditLogger->record($user, 'verification_email_resent', $request);

        return back()->with('status', 'verification-link-sent');
    }
}
