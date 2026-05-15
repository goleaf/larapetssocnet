<?php

declare(strict_types=1);

namespace App\Actions\Engagement;

use App\Models\Identity\User;
use App\Models\Moderation\Report;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Model;

class CreateReportAction
{
    public function __construct(private readonly ReportService $reports) {}

    public function handle(User $reporter, Model $reportable, string $reason, ?string $details = null): Report
    {
        return $this->reports->create($reporter, $reportable, $reason, $details);
    }
}
