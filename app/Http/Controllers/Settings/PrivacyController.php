<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrivacyController extends Controller
{
    public function show(): View
    {
        return view('settings.privacy', ['user' => auth()->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'is_private' => ['required', 'boolean'],
        ]);

        app(SettingsService::class)->updatePrivacy(
            auth()->user(),
            (bool) $request->is_private,
        );

        return back()->with('success', 'Privacy settings saved.');
    }
}
