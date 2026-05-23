<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RequestPasswordResetLinkAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    public function store(Request $request, RequestPasswordResetLinkAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));
        $message = $action->handle($email, $request, 'fallback_controller');

        return back()
            ->withInput(['email' => $email])
            ->with('status', $message);
    }
}
