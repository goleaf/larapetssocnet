<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RunMaintenanceTaskRequest;
use App\Services\Maintenance\MaintenanceTaskService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class MaintenanceController extends Controller
{
    public function index(MaintenanceTaskService $tasks): View
    {
        return view('admin.maintenance.index', [
            'tasks' => $tasks->tasks(),
        ]);
    }

    public function run(string $task, RunMaintenanceTaskRequest $request, MaintenanceTaskService $tasks): RedirectResponse
    {
        abort_unless(array_key_exists($task, $tasks->tasks()), 404);

        $result = $tasks->run($task, $request->taskOptions());

        return redirect()
            ->route('admin.maintenance.index')
            ->with('maintenance_result', $result->toArray());
    }
}
