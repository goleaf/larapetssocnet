<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRegisteredUserRequest;
use App\Models\Identity\User;
use App\Services\Auth\AuthAuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        $request->session()->put('registration_form_started_at', now()->timestamp);

        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(StoreRegisteredUserRequest $request, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validated();

        $request->ensureRegistrationIsNotRateLimited();

        if ($request->trippedBotProtection()) {
            $request->hitRegistrationRateLimiter();

            $auditLogger->record(null, $request->trippedHoneypot() ? 'registration_honeypot' : 'registration_blocked', $request, [
                'honeypot' => $request->trippedHoneypot(),
                'timing' => $request->trippedTimingTrap(),
                'user_agent' => $request->hasSuspiciousUserAgent(),
            ]);

            return redirect()
                ->route('login')
                ->with('status', 'If that account can be created, a verification email will be sent shortly.');
        }

        $request->hitRegistrationRateLimiter();

        $username = (string) $validated['username'];
        $now = now();

        $user = DB::transaction(function () use ($request, $auditLogger, $validated, $username, $now): User {
            $user = User::query()->create(array_merge(
                User::defaultRegistrationPrivacySettings(),
                [
                    'name' => $validated['name'],
                    'display_name' => $validated['name'],
                    'username' => $username,
                    'email' => $validated['email'],
                    'password' => Hash::make((string) $validated['password']),
                    'birth_date' => $validated['birth_date'],
                    'bio' => null,
                    'location' => null,
                    'avatar_path' => null,
                    'cover_photo_path' => null,
                    'profile_photo_path' => null,
                    'notification_preferences' => User::defaultNotificationPreferences(),
                    'terms_accepted_at' => $now,
                    'terms_version' => User::CURRENT_TERMS_VERSION,
                    'registration_ip_address' => $request->ip(),
                    'registration_user_agent' => $request->userAgent(),
                    'role' => 'member',
                    'onboarding_step' => '1',
                    'followers_count' => 0,
                    'following_count' => 0,
                    'follow_requests_count' => 0,
                    'following_pets_count' => 0,
                    'pets_count' => 0,
                    'posts_count' => 0,
                    'blocked_users_count' => 0,
                    'blocked_by_count' => 0,
                ],
            ));

            if ($user->username !== $username) {
                throw ValidationException::withMessages([
                    'username' => 'Username is already taken.',
                ]);
            }

            $auditLogger->record($user, 'registration', $request, [
                'method' => 'email',
                'email_verification_pending' => true,
                'terms_version' => User::CURRENT_TERMS_VERSION,
                'terms_accepted' => true,
            ]);

            return $user;
        });

        event(new Registered($user));

        $auditLogger->record($user, 'verification_email_sent', $request);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('registration_form_started_at');

        return redirect()
            ->route('verification.notice')
            ->with('status', 'Account created. Please verify your email.');
    }
}
