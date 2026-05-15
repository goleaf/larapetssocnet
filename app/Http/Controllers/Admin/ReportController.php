<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Moderation\Report;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->status ?? 'pending';

        $reports = Report::where('status', $status)
            ->with(['reporter', 'reportable'])
            ->latest()
            ->paginate(20);

        return view('admin.reports.index', ['reports' => $reports, 'status' => $status]);
    }

    public function show(Report $report): View
    {
        $report->load(['reporter', 'reportable']);

        return view('admin.reports.show', ['report' => $report]);
    }

    public function resolve(Request $request, Report $report): JsonResponse|RedirectResponse
    {
        $request->validate([
            'status' => 'required|in:reviewed,dismissed,actioned',
        ]);

        app(ModerationService::class)->resolveReport(
            $report,
            auth()->user(),
            $request->status,
        );

        if ($request->expectsJson() || $request->isJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('success', 'Report updated.');
    }
}
