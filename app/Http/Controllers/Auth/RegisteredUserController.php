<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegisteredUserRequest;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use App\Services\UsernameService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(StoreRegisteredUserRequest $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->trippedHoneypot()) {
            $auditLogger->record(null, 'registration_honeypot', $request);

            return redirect()
                ->route('login')
                ->with('status', 'If that account can be created, a verification email will be sent shortly.');
        }

        $username = (string) ($validated['username'] ?? '');
        if ($username === '') {
            $username = app(UsernameService::class)->generate((string) $validated['name']);
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => $username,
            'email' => $validated['email'],
            'password' => Hash::make((string) $validated['password']),
            'birth_date' => $validated['birth_date'],
            'onboarding_step' => 1,
        ]);

        event(new Registered($user));

        $auditLogger->record($user, 'registration', $request);

        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
