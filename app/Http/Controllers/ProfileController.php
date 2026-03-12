<?php

namespace App\Http\Controllers;

use App\Enums\FollowAbility;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Support\Usernames\UsernameNormalizer;
use App\Support\Usernames\UsernameRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Mews\Purifier\Facades\Purifier;

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

    public function edit(Request $request): View
    {
        $user = $request->user();
        $this->authorize('update', $user);

        return view('settings.profile', [
            'user' => $user,
        ]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $this->authorize('update', $user);

        $validated = $request->validated();

        $bioHtml = $this->sanitizeBioHtml($validated['bio'] ?? null);
        $plainBio = $bioHtml ? trim(strip_tags($bioHtml)) : null;

        $username = User::normalizeUsername((string) ($validated['username'] ?? ''));
        if ($username === '') {
            $username = $user->username ?: User::generateUniqueUsername((string) ($validated['name'] ?? $user->name));
        }

        $location = $validated['location'] ?? $validated['city'] ?? null;

        $payload = [
            'name' => $validated['name'],
            'username' => $username,
            'email' => Str::lower((string) $validated['email']),
        ];

        if (array_key_exists('bio', $validated)) {
            $payload['bio'] = $plainBio !== '' ? $plainBio : null;
            $payload['bio_html'] = $bioHtml;
        }

        if (array_key_exists('website', $validated)) {
            $payload['website'] = $validated['website'] ?? null;
        }

        if (array_key_exists('location', $validated) || array_key_exists('city', $validated)) {
            $payload['location'] = $location;
            $payload['city'] = $validated['city'] ?? $location;
        }

        if (array_key_exists('country_code', $validated)) {
            $payload['country_code'] = $validated['country_code'] ?? null;
        }

        if (array_key_exists('birth_date', $validated)) {
            $payload['birth_date'] = $validated['birth_date'] ?? null;
        }

        if (array_key_exists('is_private', $validated)) {
            $payload['is_private'] = (bool) $validated['is_private'];
        }

        DB::transaction(function () use ($user, $payload): void {
            $user->fill($payload);

            if ($user->isDirty('email')) {
                $user->email_verified_at = null;
            }

            $user->save();
        });

        if ($request->hasFile('avatar')) {
            $user->updateAvatar($request->file('avatar'));
        }

        if ($request->hasFile('cover')) {
            $user->updateCover($request->file('cover'));
        }

        if ((bool) ($validated['remove_avatar'] ?? false)) {
            $user->clearMediaCollection(User::MEDIA_COLLECTION_AVATAR);
            $user->forceFill([
                'avatar_path' => null,
                'profile_photo_path' => null,
            ])->saveQuietly();
        }

        if ((bool) ($validated['remove_cover'] ?? false)) {
            $user->clearMediaCollection(User::MEDIA_COLLECTION_COVER);
            $user->forceFill([
                'cover_photo_path' => null,
            ])->saveQuietly();
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

    protected function sanitizeBioHtml(?string $rawBio): ?string
    {
        $rawBio = trim((string) $rawBio);

        if ($rawBio === '') {
            return null;
        }

        $cleaned = trim((string) Purifier::clean($rawBio));

        return $cleaned !== '' ? $cleaned : null;
    }
}
