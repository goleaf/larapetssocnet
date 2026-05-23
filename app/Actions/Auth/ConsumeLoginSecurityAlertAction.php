<?php

namespace App\Actions\Auth;

use App\Models\Moderation\Report;
use App\Models\Security\LoginSecurityAlert;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\DeviceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumeLoginSecurityAlertAction
{
    public const string STATUS_DISMISSED = 'dismissed';

    public const string STATUS_SECURED = 'secured';

    public const string STATUS_ALREADY_USED = 'already_used';

    public function __construct(
        private readonly AuthAuditLogger $auditLogger,
        private readonly DeviceSessionService $sessions,
    ) {}

    public function dismiss(LoginSecurityAlert $alert, string $token, Request $request): string
    {
        if ($alert->isConsumed()) {
            return self::STATUS_ALREADY_USED;
        }

        $this->authorizeToken($alert, $token);

        $alert->forceFill([
            'dismissed_at' => now(),
        ])->save();

        $this->auditLogger->record($alert->user()->first(), 'login_anomaly_dismissed', $request, [
            'login_security_alert_id' => $alert->getKey(),
            'country_code' => $alert->country_code,
            'country' => $alert->country,
            'city' => $alert->city,
        ]);

        return self::STATUS_DISMISSED;
    }

    public function secure(LoginSecurityAlert $alert, string $token, Request $request): string
    {
        if ($alert->isConsumed()) {
            return self::STATUS_ALREADY_USED;
        }

        $this->authorizeToken($alert, $token);

        $user = $alert->user()->firstOrFail();

        DB::transaction(function () use ($alert, $request, $user): void {
            $deletedSessions = $this->sessions->destroyAllSessions($user);

            $user->forceFill([
                'remember_token' => null,
            ])->save();

            $alert->forceFill([
                'secured_at' => now(),
            ])->save();

            $this->createModerationAlert($alert);

            $this->auditLogger->record($user, 'login_anomaly_secured', $request, [
                'login_security_alert_id' => $alert->getKey(),
                'deleted_sessions' => $deletedSessions,
                'moderation_alert' => true,
                'country_code' => $alert->country_code,
                'country' => $alert->country,
                'city' => $alert->city,
            ]);
        });

        if ($request->user()?->is($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return self::STATUS_SECURED;
    }

    private function authorizeToken(LoginSecurityAlert $alert, string $token): void
    {
        abort_unless(hash_equals((string) $alert->token_hash, hash('sha256', $token)), 403);
    }

    private function createModerationAlert(LoginSecurityAlert $alert): void
    {
        $user = $alert->user()->firstOrFail();

        $report = Report::withTrashed()->firstOrNew([
            'reporter_user_id' => $user->getKey(),
            'reportable_type' => $user->getMorphClass(),
            'reportable_id' => $user->getKey(),
            'reason' => Report::REASON_LOGIN_ANOMALY_SECURITY_ALERT,
        ]);

        $report->forceFill([
            'details' => 'The account owner used the login anomaly alert emergency link after a sign-in from '.$alert->country.'. Review active access, email safety, and recovery options.',
            'status' => Report::STATUS_PENDING,
            'priority' => Report::PRIORITY_HIGH,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ])->save();

        if ($report->trashed()) {
            $report->restore();
        }
    }
}
