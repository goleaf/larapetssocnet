<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class AccountSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $blockedUsers = $user->blockedUsers()
            ->orderBy('users.name')
            ->paginate(20);

        return view('settings.account', [
            'user' => $user,
            'blockedUsers' => $blockedUsers,
        ]);
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_private' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            'is_private' => (bool) $validated['is_private'],
        ]);

        return Redirect::route('settings.account.edit')->with('status', 'privacy-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
