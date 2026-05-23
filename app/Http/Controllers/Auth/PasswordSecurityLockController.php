<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConsumeSecurityEmergencyAction;
use App\Http\Controllers\Controller;
use App\Models\Security\AccountSecurityAction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordSecurityLockController extends Controller
{
    public function __invoke(Request $request, AccountSecurityAction $action, ConsumeSecurityEmergencyAction $consume): View
    {
        $status = $consume->handle($action, (string) $request->query('token'), $request);

        return view('auth.account-security-action', [
            'status' => $status,
        ]);
    }
}
