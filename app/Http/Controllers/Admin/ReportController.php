<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
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

        return view('admin.reports.index', compact('reports', 'status'));
    }

    public function show(Report $report): View
    {
        $report->load(['reporter', 'reportable']);

        return view('admin.reports.show', compact('report'));
    }

    public function resolve(Request $request, Report $report): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:reviewed,dismissed,actioned',
        ]);

        app(ModerationService::class)->resolveReport(
            $report,
            auth()->user(),
            $request->status,
        );

        return response()->json(['success' => true]);
    }
}
