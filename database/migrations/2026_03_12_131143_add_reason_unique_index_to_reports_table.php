<?php

use App\Models\Moderation\Report;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        $seen = [];

        Report::query()
            ->select(['reports.id', 'reports.reporter_user_id', 'reports.reportable_type', 'reports.reportable_id', 'reports.reason'])
            ->orderBy('reports.id')
            ->chunkById(500, function ($reports) use (&$seen): void {
                $reports->each(function (Report $report) use (&$seen): void {
                    $key = $report->reporter_user_id.'|'.$report->reportable_type.'|'.$report->reportable_id.'|'.$report->reason;

                    if (isset($seen[$key])) {
                        $report->delete();

                        return;
                    }

                    $seen[$key] = true;
                });
            });

        Schema::table('reports', function (Blueprint $table): void {
            $table->unique(
                ['reporter_user_id', 'reportable_type', 'reportable_id', 'reason'],
                'reports_reporter_reportable_reason_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropUnique('reports_reporter_reportable_reason_unique');
        });
    }
};
