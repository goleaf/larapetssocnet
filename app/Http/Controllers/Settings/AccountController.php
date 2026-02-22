<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(): View
    {
        return view('settings.account');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        app(SettingsService::class)->deleteAccount(
            auth()->user(),
            $request->password,
        );

        return redirect('/')->with('success', 'Your account has been deleted.');
    }
}
