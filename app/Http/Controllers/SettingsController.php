<?php

namespace App\Http\Controllers;

use App\Actions\Users\BuildProfileSettingsViewDataAction;
use App\Actions\Users\UpdateProfileAction;
use App\Exceptions\CannotBlockAdminException;
use App\Exceptions\CannotBlockSelfException;
use App\Http\Requests\BlockUserByUsernameRequest;
use App\Http\Requests\BlockUserRequest;
use App\Http\Requests\UpdateSettingsProfileRequest;
use App\Models\User;
use App\Services\AccountExportService;
use App\Services\BlockService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settingsService,
        private readonly AccountExportService $exportService,
        private readonly BlockService $blockService
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('settings.profile');
    }

    public function editProfile(Request $request, BuildProfileSettingsViewDataAction $buildProfileSettingsViewData): View
    {
        $user = $request->user();
        $this->authorize('update', $user);

        return view('settings.profile', $buildProfileSettingsViewData->handle($user));
    }

    public function updateProfile(UpdateSettingsProfileRequest $request, UpdateProfileAction $updateProfile): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $updateProfile->handle($user, $request->validated(), true);

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
        $blockedUsers = $this->blockService->getBlockedUsers($request->user());

        return view('settings.blocked', [
            'blockedUsers' => $blockedUsers,
        ]);
    }

    public function blockUser(BlockUserByUsernameRequest $request): RedirectResponse
    {
        $userToBlock = $request->target();

        try {
            $this->blockService->block($request->user(), $userToBlock);
        } catch (CannotBlockSelfException|CannotBlockAdminException $exception) {
            return back()->withErrors(['username' => $exception->getMessage()]);
        }

        return back()->with('success', "Blocked {$userToBlock->username}.");
    }

    public function unblockUser(BlockUserRequest $request, User $user): RedirectResponse
    {
        $this->blockService->unblock($request->user(), $user);

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
