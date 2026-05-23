<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreMagicLinkRequest;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\MagicLinkService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function store(StoreMagicLinkRequest $request, MagicLinkService $magicLinks, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $identifierHash = hash('sha256', (string) $request->input('email'));
        $user = User::query()
            ->where('email', $request->input('email'))
            ->first();

        if ($user instanceof User) {
            $magicLinks->createAndSend($user, $request);
            $auditLogger->record($user, 'magic_link_requested', $request, [
                'identifier_hash' => $identifierHash,
            ]);
        } else {
            $auditLogger->record(null, 'magic_link_requested', $request, [
                'identifier_hash' => $identifierHash,
                'matched_user' => false,
            ]);
        }

        return back()->with('status', 'If an account exists for that email, a secure sign-in link has been sent.');
    }

    public function consume(Request $request, string $token, MagicLinkService $magicLinks, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $plainToken = (string) $request->query('secret');
        $magicToken = $magicLinks->consume($token, $plainToken);

        if ($magicToken === null || ! $magicToken->user instanceof User) {
            $auditLogger->record(null, 'magic_link_rejected', $request, [
                'token_hash' => hash('sha256', $plainToken),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => 'This sign-in link is invalid or has expired.',
            ]);
        }

        $user = $magicToken->user;

        if ((bool) $user->is_banned || $user->trashed()) {
            $auditLogger->record($user, 'magic_link_rejected', $request, [
                'restriction_reason' => (bool) $user->is_banned ? 'banned' : 'deleted',
            ]);

            return redirect()->route('login')->withErrors([
                'email' => trans('auth.failed'),
            ]);
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        $restrictedRoute = $this->restrictedRouteFor($user);

        if ($restrictedRoute !== null) {
            $auditLogger->record($user, 'magic_link_restricted', $request, [
                'token_id' => $magicToken->getKey(),
                'restriction_route' => $restrictedRoute,
            ]);

            return redirect()->route($restrictedRoute);
        }

        $auditLogger->record($user, 'magic_link_accepted', $request, [
            'token_id' => $magicToken->getKey(),
            'remember' => false,
            'link_fingerprint' => Str::substr(hash('sha256', $plainToken), 0, 12),
            'two_factor_required' => $user->two_factor_secret !== null,
        ]);

        if ($user->two_factor_secret !== null) {
            $request->session()->put('auth.two_factor_pending_user_id', $user->getKey());

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->forget('auth.two_factor_pending_user_id');

        $user->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

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
