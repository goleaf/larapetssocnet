<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportThresholdReached;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public const THRESHOLD = 5;

    public function create(User $reporter, Model $reportable, string $reason, ?string $details = null): Report
    {
        $normalizedReason = strtolower(trim($reason));

        if (! in_array($normalizedReason, Report::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => 'Invalid report reason.']);
        }

        if ($this->isSelfReport($reporter, $reportable)) {
            throw ValidationException::withMessages(['report' => 'You cannot report your own content.']);
        }

        return DB::transaction(function () use ($reporter, $reportable, $normalizedReason, $details): Report {
            $existing = Report::query()
                ->where('reporter_user_id', $reporter->id)
                ->where('reportable_type', $reportable::class)
                ->where('reportable_id', $reportable->getKey())
                ->where('reason', $normalizedReason)
                ->first();

            if ($existing) {
                if ($existing->status === Report::STATUS_PENDING) {
                    if ($details !== null && $details !== '' && $details !== $existing->details) {
                        $existing->update(['details' => $details]);
                    }

                    return $existing;
                }

                return $existing;
            }

            $report = Report::query()->create([
                'reporter_user_id' => $reporter->id,
                'reportable_type' => $reportable::class,
                'reportable_id' => $reportable->getKey(),
                'reason' => $normalizedReason,
                'details' => $details,
                'status' => Report::STATUS_PENDING,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
            ]);

            $this->notifyAdminsIfThresholdReached($reportable);

            return $report;
        });
    }

    private function isSelfReport(User $reporter, Model $reportable): bool
    {
        if ($reportable instanceof User) {
            return $reporter->is($reportable);
        }

        if ($reportable instanceof Post || $reportable instanceof Comment) {
            return (int) $reporter->getKey() === (int) $reportable->user_id;
        }

        return false;
    }

    private function notifyAdminsIfThresholdReached(Model $reportable): void
    {
        $pendingCount = Report::query()
            ->pending()
            ->where('reportable_type', $reportable::class)
            ->where('reportable_id', $reportable->getKey())
            ->count();

        if ($pendingCount !== self::THRESHOLD) {
            return;
        }

        $admins = User::query()->role('admin')->get(['users.id', 'users.name', 'users.email']);

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new ReportThresholdReached($reportable, $pendingCount));
    }
}
