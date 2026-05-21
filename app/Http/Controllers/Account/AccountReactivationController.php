<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ReactivateAccountRequest;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountReactivationController extends Controller
{
    public function show(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        if ($user->deactivated_at === null) {
            return $user->hasVerifiedEmail()
                ? redirect()->route('dashboard')
                : redirect()->route('verification.notice');
        }

        return view('auth.account-reactivate', [
            'user' => $user,
        ]);
    }

    public function store(ReactivateAccountRequest $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'deactivated_at' => null,
            'deactivation_reason' => null,
        ])->save();

        $auditLogger->record($user, 'account_reactivated', $request);

        return $user->hasVerifiedEmail()
            ? redirect()->route('dashboard')->with('status', 'account-reactivated')
            : redirect()->route('verification.notice')->with('status', 'account-reactivated');
    }
}
