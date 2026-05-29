<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RequestMagicLoginLinkAction;
use App\Enums\AccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreMagicLinkRequest;
use App\Jobs\Auth\DetectLoginAnomaly;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\GeoIpLookupService;
use App\Services\Auth\MagicLinkConsumptionResult;
use App\Services\Auth\MagicLinkService;
use App\Services\Auth\UserAgentDetailsService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function store(StoreMagicLinkRequest $request, RequestMagicLoginLinkAction $action): RedirectResponse
    {
        return back()->with('status', $action->handle(
            email: (string) $request->input('email'),
            request: $request,
        ));
    }

    public function consume(
        Request $request,
        string $token,
        MagicLinkService $magicLinks,
        AuthAuditLogger $auditLogger,
        GeoIpLookupService $geoIp,
        UserAgentDetailsService $userAgents,
    ): RedirectResponse {
        $result = $magicLinks->consume($token);
        $tokenHash = hash('sha256', trim($token));

        if (! $result->successful()) {
            $auditLogger->record($result->token?->user, 'magic_link_rejected', $request, array_filter([
                'token_id' => $result->token?->getKey(),
                'token_hash' => $tokenHash,
                'failure_reason' => $result->status,
            ], static fn (mixed $value): bool => $value !== null));

            return redirect()->route('login')->withErrors([
                'email' => $this->failureMessageFor($result->status),
            ]);
        }

        $magicToken = $result->token;
        $user = $magicToken?->user;

        if (! $user instanceof User) {
            $auditLogger->record(null, 'magic_link_rejected', $request, [
                'token_id' => $magicToken?->getKey(),
                'token_hash' => $tokenHash,
                'failure_reason' => 'missing_user',
            ]);

            return redirect()->route('login')->withErrors([
                'email' => $this->failureMessageFor(MagicLinkConsumptionResult::INVALID),
            ]);
        }

        if ((bool) $user->is_banned || $user->trashed()) {
            $auditLogger->record($user, 'magic_link_rejected', $request, [
                'token_id' => $magicToken->getKey(),
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

        $statusFailure = $this->statusFailure($user);

        if ($statusFailure !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $auditLogger->record($user, 'magic_link_rejected', $request, [
                'token_id' => $magicToken->getKey(),
                'failure_reason' => $statusFailure['reason'],
            ]);

            return redirect()->route('login')->withErrors([
                'email' => $statusFailure['message'],
            ]);
        }

        $requiresTwoFactor = $user->two_factor_secret !== null;
        $loginAt = now();

        $updates = [
            'failed_login_attempts' => 0,
            'last_failed_login_at' => null,
            'last_active_at' => $loginAt,
        ];

        if (! $requiresTwoFactor) {
            $updates['last_login_at'] = $loginAt;
        }

        $user->forceFill($updates)->save();

        if ($requiresTwoFactor) {
            $request->session()->put('auth.two_factor_pending_user_id', $user->getKey());
        } else {
            $request->session()->forget('auth.two_factor_pending_user_id');
        }

        $metadata = [
            'token_id' => $magicToken->getKey(),
            'remember' => false,
            'link_fingerprint' => Str::substr($tokenHash, 0, 12),
            'two_factor_required' => $requiresTwoFactor,
        ];

        if (! $requiresTwoFactor) {
            $metadata = array_merge($metadata, $this->loginContextMetadata($request, $geoIp, $userAgents));
        }

        $auditLogger->record($user, 'magic_link_accepted', $request, $metadata);

        if ($requiresTwoFactor) {
            return redirect()->route('two-factor.challenge');
        }

        DetectLoginAnomaly::dispatchForRequest($user, $request, $loginAt);

        return $user->hasCompletedOnboarding()
            ? redirect()->route('feed.index')
            : redirect()->route('onboarding.show');
    }

    private function failureMessageFor(string $status): string
    {
        return match ($status) {
            MagicLinkConsumptionResult::EXPIRED => 'This login link has expired. Request a new login link to continue.',
            MagicLinkConsumptionResult::USED => 'This login link has already been used. Request a new login link to continue.',
            default => 'This login link is invalid. Request a new login link to continue.',
        };
    }

    /**
     * @return array{reason: string, message: string}|null
     */
    private function statusFailure(User $user): ?array
    {
        if ($user->hasAccountStatus(AccountStatus::Deactivated)) {
            return [
                'reason' => 'deactivated',
                'message' => 'This account is deactivated. Reactivation is required before signing in.',
            ];
        }

        if ($user->hasAccountStatus(AccountStatus::Suspended)) {
            return [
                'reason' => 'suspended',
                'message' => 'This account is suspended and cannot sign in right now.',
            ];
        }

        if ($user->hasAccountStatus(AccountStatus::PendingDeletion)) {
            return [
                'reason' => 'pending_deletion',
                'message' => 'This account is pending deletion. Contact support if you need help recovering it.',
            ];
        }

        return null;
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

    /**
     * @return array{country_code: string|null, country: string, city: string|null, device_type: string, browser_name: string, browser_version: string|null, os_name: string, os_version: string|null}
     */
    private function loginContextMetadata(Request $request, GeoIpLookupService $geoIp, UserAgentDetailsService $userAgents): array
    {
        $location = $geoIp->lookup($request->ip());
        $device = $userAgents->parse($request->userAgent());

        return [
            'country_code' => $location['country_code'],
            'country' => $location['country'],
            'city' => $location['city'],
            'device_type' => $device['device_type'],
            'browser_name' => $device['browser_name'],
            'browser_version' => $device['browser_version'],
            'os_name' => $device['os_name'],
            'os_version' => $device['os_version'],
        ];
    }
}
