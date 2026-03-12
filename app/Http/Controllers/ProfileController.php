<?php

namespace App\Http\Controllers;

use App\Actions\Users\BuildProfileSettingsViewDataAction;
use App\Actions\Users\UpdateProfileAction;
use App\Enums\FollowAbility;
use App\Exceptions\UsernameChangeCooldownException;
use App\Exceptions\UsernameNotAvailableException;
use App\Exceptions\UsernameReservedException;
use App\Http\Requests\UpdateSettingsProfileRequest;
use App\Models\User;
use App\Services\ProfileVisibilityService;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * @var list<string>
     */
    protected array $allowedTabs = ['posts', 'pets', 'photos', 'likes'];

    public function show(Request $request, User $user): View
    {
        $this->authorize('view', $user);

        $viewer = $request->user();
        $tab = in_array($request->string('tab')->toString(), $this->allowedTabs, true)
            ? $request->string('tab')->toString()
            : 'posts';

        $canViewContent = $user->canBeViewedBy($viewer);
        $profileVisibility = app(ProfileVisibilityService::class)->resolve($user);

        $user->loadCount(['followers', 'following', 'pets']);

        $pets = collect();
        if ($tab === 'pets' && $canViewContent) {
            $petsQuery = $user->pets()->latest();

            if (! $viewer || ! $viewer->is($user)) {
                $petsQuery->public();
            }

            $pets = $petsQuery->get();
        }

        $photos = collect();
        if ($tab === 'photos' && $canViewContent) {
            $photos = collect($user->getMedia(User::MEDIA_COLLECTION_PHOTOS))
                ->merge($user->getMedia(User::MEDIA_COLLECTION_AVATAR))
                ->merge($user->getMedia(User::MEDIA_COLLECTION_COVER));
        }

        $followStatus = $viewer ? $viewer->getFollowStatus($user) : 'none';

        return view('profile.show', [
            'profileUser' => $user,
            'tab' => $tab,
            'canViewContent' => $canViewContent,
            'profileVisibility' => $profileVisibility->value,
            'profileVisibilityLabel' => $profileVisibility->label(),
            'profileVisibilityIcon' => $profileVisibility->icon(),
            'pets' => $pets,
            'photos' => $photos,
            'posts' => collect(),
            'likes' => collect(),
            'followStatus' => $followStatus,
            'isFollowing' => $followStatus === 'following',
            'isBlocked' => $viewer ? $viewer->hasBlocked($user) : false,
            'isBlockedBy' => $viewer ? $viewer->isBlockedBy($user) : false,
        ]);
    }

    public function edit(Request $request, BuildProfileSettingsViewDataAction $buildProfileSettingsViewData): View
    {
        $user = $request->user();
        $this->authorize('update', $user);

        return view('settings.profile', $buildProfileSettingsViewData->handle($user));
    }

    public function update(UpdateSettingsProfileRequest $request, UpdateProfileAction $updateProfile): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        try {
            $updateProfile->handle($user, $request->validated());
        } catch (UsernameChangeCooldownException|UsernameReservedException|UsernameNotAvailableException $exception) {
            return back()
                ->withInput()
                ->withErrors(['username' => $exception->getMessage()]);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function avatarUpdate(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorize('updateAvatar', $user);

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $user->updateAvatar($validated['avatar']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'avatar_url' => $user->fresh()->avatar_url,
            ]);
        }

        return back()->with('status', 'profile-avatar-updated');
    }

    public function coverUpdate(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorize('updateCover', $user);

        $validated = $request->validate([
            'cover' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $user->updateCover($validated['cover']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'cover_photo_url' => $user->fresh()->cover_photo_url,
            ]);
        }

        return back()->with('status', 'profile-cover-updated');
    }

    public function followers(Request $request, User $user): View
    {
        $this->authorize(FollowAbility::ViewFollowers, $user);

        $followers = $user->followers()
            ->notBlockedFor($request->user())
            ->withCount(['followers', 'following'])
            ->orderBy('users.name')
            ->paginate(20);

        return view('profile.followers', [
            'profileUser' => $user,
            'followers' => $followers,
        ]);
    }

    public function following(Request $request, User $user): View
    {
        $this->authorize(FollowAbility::ViewFollowing, $user);

        $following = $user->following()
            ->notBlockedFor($request->user())
            ->withCount(['followers', 'following'])
            ->orderBy('users.name')
            ->paginate(20);

        return view('profile.following', [
            'profileUser' => $user,
            'following' => $following,
        ]);
    }

    public function usernameAvailable(Request $request): JsonResponse
    {
        $normalizedUsername = UsernameNormalizer::normalize((string) $request->input('username'));

        $validator = Validator::make([
            'username' => $normalizedUsername,
        ], [
            'username' => UsernameRules::requiredRules($request->user()?->id),
        ], [
            'username.min' => 'Username must be '.UsernameRules::minLength().'-'.UsernameRules::maxLength().' characters.',
            'username.max' => 'Username must be '.UsernameRules::minLength().'-'.UsernameRules::maxLength().' characters.',
            'username.regex' => 'Only letters, numbers and underscores allowed.',
            'username.unique' => 'Username is already taken.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'available' => false,
                'message' => $validator->errors()->first('username'),
            ]);
        }

        return response()->json([
            'available' => true,
            'message' => 'Username is available!',
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $this->authorize('update', $user);

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
