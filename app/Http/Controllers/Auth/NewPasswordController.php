<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreNewPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(StoreNewPasswordRequest $request, ResetPasswordAction $resetPassword): RedirectResponse
    {
        $resetPassword->reset(
            token: (string) $request->validated('token'),
            email: (string) $request->validated('email'),
            password: (string) $request->validated('password'),
            request: $request,
        );

        $request->session()->regenerate();

        return redirect()
            ->route('feed.index')
            ->with('status', ResetPasswordAction::SUCCESS_MESSAGE);
    }
}
