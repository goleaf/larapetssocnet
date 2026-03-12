<?php

namespace App\Actions\Engagement;

use App\Models\Report;
use App\Models\User;
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
