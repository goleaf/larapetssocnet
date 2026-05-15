<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = app(AdminService::class)->getStats();

        return view('admin.dashboard', ['stats' => $stats]);
    }
}
