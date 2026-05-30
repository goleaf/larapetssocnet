<?php

namespace App\Services;

use App\Models\Content\Comment;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Notifications\Database\Moderation\ProfileReportSubmitted;
use App\Notifications\Database\Moderation\ReportThresholdReached;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

        if (! in_array($normalizedReason, $this->allowedReasonsFor($reportable), true)) {
            throw ValidationException::withMessages(['reason' => 'Invalid report reason.']);
        }

        if ($this->isSelfReport($reporter, $reportable)) {
            throw ValidationException::withMessages(['report' => 'You cannot report your own content.']);
        }

        return DB::transaction(function () use ($reporter, $reportable, $normalizedReason, $details): Report {
            $existing = Report::query()
                ->where('reporter_user_id', $reporter->id)
                ->where('reportable_type', $reportable->getMorphClass())
                ->where('reportable_id', $reportable->getKey())
                ->where('reason', $normalizedReason)
                ->first();

            if ($existing) {
                if ($existing->status === Report::STATUS_PENDING) {
                    if (! in_array($details, [null, '', $existing->details], true)) {
                        $existing->update(['details' => $details]);
                    }

                    return $existing;
                }

                return $existing;
            }

            $report = Report::query()->create([
                'reporter_user_id' => $reporter->id,
                'reportable_type' => $reportable->getMorphClass(),
                'reportable_id' => $reportable->getKey(),
                'reason' => $normalizedReason,
                'details' => $details,
                'status' => Report::STATUS_PENDING,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
            ]);

            if ($reportable instanceof User) {
                $this->notifyModerationTeamOfProfileReport($report, $reporter, $reportable);
            }

            $this->notifyAdminsIfThresholdReached($reportable);

            return $report;
        });
    }

    /**
     * @return list<string>
     */
    private function allowedReasonsFor(Model $reportable): array
    {
        return $reportable instanceof User ? Report::PROFILE_REASONS : Report::REASONS;
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
            ->where('reportable_type', $reportable->getMorphClass())
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

    private function notifyModerationTeamOfProfileReport(Report $report, User $reporter, User $reportedUser): void
    {
        $moderators = $this->moderationTeam();

        if ($moderators->isEmpty()) {
            return;
        }

        Notification::send($moderators, new ProfileReportSubmitted($report, $reporter, $reportedUser));
    }

    /**
     * @return Collection<int, User>
     */
    private function moderationTeam(): Collection
    {
        return User::query()
            ->select(['users.id', 'users.name', 'users.email', 'users.role'])
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('users.role', ['admin', 'moderator'])
                    ->orWhereHas('roles', function (Builder $roleQuery): void {
                        $roleQuery->whereIn('name', ['admin', 'moderator']);
                    });
            })
            ->get()
            ->unique('id')
            ->values();
    }
}
