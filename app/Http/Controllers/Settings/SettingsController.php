<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\BuildProfileSettingsViewDataAction;
use App\Actions\Users\UpdateProfileAction;
use App\Exceptions\CannotBlockAdminException;
use App\Exceptions\CannotBlockSelfException;
use App\Exceptions\UsernameChangeCooldownException;
use App\Exceptions\UsernameNotAvailableException;
use App\Exceptions\UsernameReservedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdatePasswordSettingsRequest;
use App\Http\Requests\Settings\UpdatePrivacySettingsRequest;
use App\Http\Requests\Settings\UpdateSettingsProfileRequest;
use App\Http\Requests\Social\BlockUserByUsernameRequest;
use App\Http\Requests\Social\BlockUserRequest;
use App\Models\Identity\User;
use App\Services\AccountExportService;
use App\Services\Auth\AuthAuditLogger;
use App\Services\Auth\DeviceSessionService;
use App\Services\BlockService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        try {
            $updateProfile->handle($user, $request->validated(), true);
        } catch (UsernameChangeCooldownException|UsernameReservedException|UsernameNotAvailableException $exception) {
            return back()
                ->withInput()
                ->withErrors(['username' => $exception->getMessage()]);
        }

        return redirect()->route('settings.profile')->with('success', 'Profile updated successfully.');
    }

    public function editPassword(): View
    {
        return view('settings.password');
    }

    public function updatePassword(UpdatePasswordSettingsRequest $request, DeviceSessionService $sessions, AuthAuditLogger $auditLogger): RedirectResponse
    {
        $validated = $request->validated();

        $this->settingsService->changePassword(
            $request->user(),
            $validated['current_password'],
            $validated['password']
        );

        $deletedSessions = $sessions->destroyOtherSessions($request->user(), $request->session()->getId());

        $auditLogger->record($request->user(), 'password_change', $request, [
            'deleted_sessions' => $deletedSessions,
        ]);

        return redirect()->route('settings.password')->with('success', 'Password updated successfully.');
    }

    public function editPrivacy(Request $request): View
    {
        return view('settings.privacy', [
            'user' => $request->user(),
        ]);
    }

    public function updatePrivacy(UpdatePrivacySettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $settings = array_merge([
            'show_in_explore' => false,
            'open_following' => false,
            'privacy_display_last_seen' => false,
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

        return response()->streamDownload(function () use ($data): void {
            echo json_encode($data, JSON_PRETTY_PRINT);
        }, 'account-data-'.now()->format('Y-m-d').'.json', [
            'Content-Type' => 'application/json',
        ]);
    }
}
