<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountExportService;
use App\Services\SettingsService;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly AccountExportService $exportService
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('settings.profile');
    }

    public function editProfile(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $normalizedUsername = UsernameNormalizer::normalize((string) $request->input('username'));

        $request->merge([
            'username' => $normalizedUsername,
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => UsernameRules::requiredRules($user->id),
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'bio' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', 'in:male,female,other,prefer_not_to_say'],
            'username_confirm' => [
                Rule::requiredIf($normalizedUsername !== $user->username),
                'nullable',
                'string',
                'in:'.$user->username,
            ],
        ], [
            'username_confirm.in' => 'You must type your CURRENT username exactly to confirm the change.',
            'username_confirm.required' => 'Confirming your current username is required.',
        ]);

        try {
            $this->settingsService->updateProfile($user, $validated, $request->input('username_confirm'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['username_confirm' => $e->getMessage()]);
        }

        return redirect()->route('settings.profile')->with('success', 'Profile updated successfully.');
    }

    public function editPassword(): View
    {
        return view('settings.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->settingsService->changePassword(
            $request->user(),
            $validated['current_password'],
            $validated['password']
        );

        return redirect()->route('settings.password')->with('success', 'Password updated successfully.');
    }

    public function editPrivacy(Request $request): View
    {
        return view('settings.privacy', [
            'user' => $request->user(),
        ]);
    }

    public function updatePrivacy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'profile_visibility' => ['required', 'string', 'in:public,followers_only'],
            'messaging_permission' => ['required', 'string', 'in:everyone,followers_only'],
            'pets_visibility' => ['required', 'string', 'in:everyone,followers_only'],
            'groups_visibility' => ['required', 'string', 'in:everyone,followers_only'],
            'show_in_explore' => ['boolean'],
            'open_following' => ['boolean'],
        ]);

        $settings = array_merge([
            'show_in_explore' => false,
            'open_following' => false,
        ], $validated);

        $this->settingsService->savePrivacySettings($request->user(), $settings);

        return redirect()->route('settings.privacy')->with('success', 'Privacy settings updated.');
    }

    public function editNotifications(Request $request): View
    {
        return view('settings.notifications', [
            'user' => $request->user(),
        ]);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notifications' => ['nullable', 'array'],
            'notifications.*' => ['boolean'],
        ]);

        $preferences = $validated['notifications'] ?? [];

        $this->settingsService->saveNotificationPreferences($request->user(), $preferences);

        return redirect()->route('settings.notifications')->with('success', 'Notification preferences updated.');
    }

    public function blockedUsers(Request $request): View
    {
        $blockedUsers = $request->user()->blockedUsers()->paginate(20);

        return view('settings.blocked', [
            'blockedUsers' => $blockedUsers,
        ]);
    }

    public function blockUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'exists:users,username'],
        ]);

        $userToBlock = User::where('username', $validated['username'])->firstOrFail();

        if ($userToBlock->id === $request->user()->id) {
            return back()->withErrors(['username' => 'You cannot block yourself.']);
        }

        $this->settingsService->blockUser($request->user(), $userToBlock);

        return back()->with('success', "Blocked {$userToBlock->username}.");
    }

    public function unblockUser(Request $request, User $user): RedirectResponse
    {
        $this->settingsService->unblockUser($request->user(), $user);

        return back()->with('success', "Unblocked {$user->username}.");
    }

    public function editData(Request $request): View
    {
        return view('settings.data', [
            'user' => $request->user(),
        ]);
    }

    public function exportData(Request $request): StreamedResponse
    {
        $data = $this->exportService->exportData($request->user());

        return response()->streamDownload(function () use ($data) {
            echo json_encode($data, JSON_PRETTY_PRINT);
        }, 'account-data-'.now()->format('Y-m-d').'.json', [
            'Content-Type' => 'application/json',
        ]);
    }
}
