<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ConsumeLoginSecurityAlertAction;
use App\Http\Controllers\Controller;
use App\Models\Security\LoginSecurityAlert;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginSecurityAlertController extends Controller
{
    public function dismiss(Request $request, LoginSecurityAlert $alert, ConsumeLoginSecurityAlertAction $consume): View
    {
        $status = $consume->dismiss($alert, (string) $request->query('token'), $request);

        return view('auth.login-security-alert-action', [
            'mode' => 'dismiss',
            'status' => $status,
        ]);
    }

    public function secure(Request $request, LoginSecurityAlert $alert, ConsumeLoginSecurityAlertAction $consume): View
    {
        $status = $consume->secure($alert, (string) $request->query('token'), $request);

        return view('auth.login-security-alert-action', [
            'mode' => 'secure',
            'status' => $status,
        ]);
    }
}
