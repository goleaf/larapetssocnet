<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DestroyOtherSessionsRequest;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\DeviceSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DeviceSessionController extends Controller
{
    public function destroyOther(DestroyOtherSessionsRequest $request, DeviceSessionService $sessions, AuthAuditLogger $auditLogger): RedirectResponse
    {
        Auth::logoutOtherDevices((string) $request->input('password'));

        $deleted = $sessions->destroyOtherSessions($request->user(), $request->session()->getId());

        $request->user()->forceFill([
            'remember_token' => null,
        ])->saveQuietly();

        $auditLogger->record($request->user(), 'other_sessions_logged_out', $request, [
            'deleted_sessions' => $deleted,
            'remember_token_cleared' => true,
        ]);

        return back()->with('success', 'You have been logged out of all other devices.');
    }
}
