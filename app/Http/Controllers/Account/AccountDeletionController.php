<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountDeletionController extends Controller
{
    public function __construct(private readonly SettingsService $settingsService) {}

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'delete_confirmation' => ['required', 'string', 'in:DELETE'],
            'password' => ['required', 'current_password'],
            'deletion_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $this->settingsService->initiateDeletion($user, $request->input('deletion_reason'));

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been scheduled for deletion. You have 30 days to log back in and cancel this process.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Ensure we only process cancellation if they actually have a deletion scheduled
        if ($user->scheduled_deletion_at) {
            $this->settingsService->cancelDeletion($user);

            return redirect()->route('dashboard')->with('success', 'Your account deletion has been cancelled. Welcome back!');
        }

        return redirect()->route('dashboard');
    }
}
