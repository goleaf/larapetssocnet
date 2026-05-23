<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\DeviceSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request, AuthAuditLogger $auditLogger, DeviceSessionService $sessions): RedirectResponse
    {
        $validated = $request->validated();

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
        ]);

        $deletedSessions = $sessions->destroyOtherSessions($request->user(), $request->session()->getId());

        $auditLogger->record($request->user(), 'password_change', $request, [
            'deleted_sessions' => $deletedSessions,
        ]);

        return back()->with('status', 'password-updated');
    }
}
