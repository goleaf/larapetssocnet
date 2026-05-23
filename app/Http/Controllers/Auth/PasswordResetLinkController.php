<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $user = User::query()->where('email', $email)->first();

        $status = Password::sendResetLink(
            ['email' => $email]
        );

        $auditLogger->record($user, 'password_reset_requested', $request, [
            'identifier_hash' => hash('sha256', $email),
            'matched_user' => $user instanceof User,
            'broker_status' => $status,
        ]);

        return back()
            ->withInput(['email' => $email])
            ->with('status', __(Password::RESET_LINK_SENT));
    }
}
