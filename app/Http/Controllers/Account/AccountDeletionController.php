<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CancelPendingDeletionRequest;
use App\Services\Auth\AuthAuditLogger;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AccountDeletionController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function destroy(Request $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $request->validate([
            'delete_confirmation' => ['required', 'string', 'in:DELETE'],
            'password' => ['required', 'current_password'],
            'deletion_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $this->settingsService->initiateDeletion($user, $request->input('deletion_reason'));

        $auditLogger->record($user, 'account_deletion_scheduled', $request);
        $auditLogger->record($user, 'logout', $request, [
            'logout_type' => 'account-deletion',
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been scheduled for deletion. You have 30 days to log back in and cancel this process.');
    }

    public function pending(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->scheduled_deletion_at === null) {
            return $user->hasVerifiedEmail()
                ? redirect()->route('dashboard')
                : redirect()->route('verification.notice');
        }

        return view('auth.account-deletion-pending', [
            'user' => $user,
        ]);
    }

    public function cancel(CancelPendingDeletionRequest $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        if ($user->scheduled_deletion_at) {
            $this->settingsService->cancelDeletion($user);
            $auditLogger->record($user, 'account_deletion_cancelled', $request);

            return $user->hasVerifiedEmail()
                ? redirect()->route('dashboard')->with('success', 'Your account deletion has been cancelled. Welcome back!')
                : redirect()->route('verification.notice')->with('success', 'Your account deletion has been cancelled. Please verify your email to continue.');
        }

        return redirect()->route('dashboard');
    }
}
