<?php

namespace App\Actions\Auth;

use App\Enums\AccountStatus;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Models\Security\AccountSecurityAction;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\DeviceSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConsumeSecurityEmergencyAction
{
    public const string STATUS_LOCKED = 'locked';

    public const string STATUS_ALREADY_USED = 'already_used';

    public function __construct(
        private readonly AuthAuditLogger $auditLogger,
        private readonly DeviceSessionService $sessions,
    ) {}

    public function handle(AccountSecurityAction $action, string $token, Request $request): string
    {
        if ($action->isUsed()) {
            return self::STATUS_ALREADY_USED;
        }

        abort_if($action->isExpired(), 403);
        abort_unless(hash_equals($action->token_hash, hash('sha256', $token)), 403);

        $user = $action->user()->firstOrFail();

        DB::transaction(function () use ($action, $request, $user): void {
            $this->lockUser($user);
            $deletedSessions = $this->sessions->destroyAllSessions($user);

            $action->forceFill([
                'used_at' => now(),
            ])->save();

            $this->createModerationAlert($user);

            $this->auditLogger->record($user, 'password_reset', $request, [
                'security_action' => AccountSecurityAction::ACTION_PASSWORD_RESET_EMERGENCY_LOCK,
                'deleted_sessions' => $deletedSessions,
                'moderation_alert' => true,
            ]);
        });

        if ($request->user()?->is($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return self::STATUS_LOCKED;
    }

    private function lockUser(User $user): void
    {
        $user->forceFill([
            'account_status' => AccountStatus::Suspended,
            'suspended_until' => null,
            'suspension_reason' => 'Emergency lock from password-change security alert.',
            'remember_token' => null,
        ])->save();
    }

    private function createModerationAlert(User $user): void
    {
        $report = Report::withTrashed()->firstOrNew([
            'reporter_user_id' => $user->getKey(),
            'reportable_type' => $user->getMorphClass(),
            'reportable_id' => $user->getKey(),
            'reason' => Report::REASON_PASSWORD_RESET_EMERGENCY_LOCK,
        ]);

        $report->forceFill([
            'details' => 'The account owner used the password-change security alert emergency link. Review account access and recovery options before lifting the suspension.',
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
