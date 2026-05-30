<?php

use App\Enums\ProfileVisibility;
use App\Http\Controllers\Profile\PublicProfileController;
use App\Models\Identity\User;
use App\Models\Social\Block;
use App\Models\Social\Follow;
use App\Services\Auth\AuthAuditLogger;
use App\Support\Usernames\UsernameNormalizer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

new
#[Layout('layouts.livewire-pass-through')]
class extends Component
{
    public User $profileOwner;

    public string $activeTab = 'posts';

    public bool $showPrivateProfile = false;

    public string $followStatus = 'none';

    public string $profileVisibility = 'public';

    public bool $showEditProfileModal = false;

    public ?string $editProfileFocusTarget = null;

    private const ALLOWED_TABS = ['posts', 'pets', 'photos', 'about', 'likes', 'groups', 'events', 'contests', 'scheduled'];

    public function mount(string $user): void
    {
        $this->profileOwner = $this->resolveActiveProfileOwner($user);

        $viewer = request()->user() ?: auth()->user();

        $this->abortIfBlocked($viewer, $this->profileOwner);
        $this->redirectRestrictedViewer($viewer);
        $this->activeTab = $this->resolveInitialTab();
        $this->hideOwnerOnlyTabsFromVisitors($viewer);
        $this->markPrivateProfileStateWhenHidden($viewer, $this->profileOwner);

        if ($this->showPrivateProfile) {
            return;
        }

        $this->redirectCanonicalUsername($user, $this->profileOwner);

        $this->profileOwner = $this->loadHeaderProfileData($this->profileOwner);
    }

    #[Renderless]
    public function saveCoverPosition(mixed $position): float
    {
        $viewer = request()->user() ?: auth()->user();

        abort_unless($viewer instanceof User, 403);

        Gate::forUser($viewer)->authorize('repositionProfileCover', $this->profileOwner);

        $validated = Validator::make(
            ['position' => $position],
            ['position' => ['required', 'numeric', 'min:0', 'max:100']],
            [
                'position.required' => 'Choose a cover focal point before saving.',
                'position.numeric' => 'Cover position must be a number.',
                'position.min' => 'Cover position must be between 0 and 100.',
                'position.max' => 'Cover position must be between 0 and 100.',
            ]
        )->validate();

        $normalizedPosition = User::normalizeCoverPhotoPosition($validated['position']);

        $this->profileOwner->forceFill([
            'cover_photo_position' => $normalizedPosition,
        ])->save();

        app(AuthAuditLogger::class)->record($viewer, 'profile_cover_position_updated', request(), [
            'position' => $normalizedPosition,
        ]);

        return $normalizedPosition;
    }

    public function activateTab(string $tab): void
    {
        $this->activeTab = $this->normalizeTab($tab);
        $this->hideOwnerOnlyTabsFromVisitors(request()->user() ?: auth()->user());
        $this->storeLastVisitedTab();
    }

    #[On('profile-pet-created')]
    public function refreshProfilePetCounts(): void
    {
        $this->profileOwner = $this->loadHeaderProfileData($this->profileOwner);
    }

    public function openEditProfileModal(?string $focusTarget = null): void
    {
        $viewer = request()->user() ?: auth()->user();

        abort_unless($viewer instanceof User, 403);

        Gate::forUser($viewer)->authorize('editProfile', $this->profileOwner);

        $this->editProfileFocusTarget = $this->sanitizeEditProfileFocusTarget($focusTarget);
        $this->showEditProfileModal = true;
    }

    #[On('profile-edit-closed')]
    public function closeEditProfileModal(): void
    {
        $this->showEditProfileModal = false;
        $this->editProfileFocusTarget = null;
    }

    #[On('profile-edit-saved')]
    public function refreshAfterProfileEdit(): void
    {
        $this->profileOwner = $this->loadHeaderProfileData($this->profileOwner);
        $this->closeEditProfileModal();
    }

    public function render(): View
    {
        if ($this->showPrivateProfile) {
            $viewer = request()->user() ?: auth()->user();

            return view('profile.private', [
                'user' => $this->profileOwner,
                'followStatus' => $this->followStatus,
                'profileVisibility' => $this->profileVisibility,
                'canMessage' => app(\App\Services\ProfileVisibilityService::class)->canMessage(
                    $viewer instanceof User ? $viewer : null,
                    $this->profileOwner
                ),
            ])->layout('layouts.livewire-pass-through');
        }

        request()->attributes->set('profile_active_tab', $this->activeTab);
        request()->attributes->set('profile_show_edit_modal', $this->showEditProfileModal);
        request()->attributes->set('profile_edit_focus_target', $this->editProfileFocusTarget);

        $response = app(PublicProfileController::class)->show(request(), $this->profileOwner);

        if ($response instanceof RedirectResponse) {
            throw new HttpResponseException($response);
        }

        return $response->layout('layouts.livewire-pass-through');
    }

    private function resolveActiveProfileOwner(string $rawUsername): User
    {
        $username = UsernameNormalizer::normalize($rawUsername);

        if ($username === '') {
            abort(404);
        }

        $user = User::query()
            ->where('username', $username)
            ->where('is_banned', false)
            ->whereNull('scheduled_deletion_at')
            ->whereNull('deactivated_at')
            ->where(function ($query): void {
                $query
                    ->whereNull('suspended_until')
                    ->orWhere('suspended_until', '<=', now());
            })
            ->first();

        if (! $user instanceof User) {
            abort(404);
        }

        return $user;
    }

    private function redirectRestrictedViewer(mixed $viewer): void
    {
        if (! $viewer instanceof User) {
            return;
        }

        if ((bool) $viewer->is_banned) {
            throw new HttpResponseException(new RedirectResponse(route('banned')));
        }

        if ($viewer->hasPendingDeletion()) {
            throw new HttpResponseException(new RedirectResponse(route('account.deletion-pending')));
        }

        if ($viewer->isDeactivated()) {
            throw new HttpResponseException(new RedirectResponse(route('account.reactivation')));
        }

        if ($viewer->isSuspended()) {
            throw new HttpResponseException(new RedirectResponse(route('account.suspended')));
        }
    }

    private function redirectCanonicalUsername(string $rawUsername, User $owner): void
    {
        if ($rawUsername === $owner->username) {
            return;
        }

        $target = route('profile.show', ['user' => $owner->username], false);
        $query = request()->getQueryString();

        if ($query) {
            $target .= '?'.$query;
        }

        throw new HttpResponseException(new RedirectResponse($target, 301));
    }

    private function abortIfBlocked(mixed $viewer, User $owner): void
    {
        if (! $viewer instanceof User) {
            return;
        }

        $blocked = Block::query()
            ->where(function ($query) use ($viewer, $owner): void {
                $query
                    ->where('blocker_id', $viewer->getKey())
                    ->where('blocked_id', $owner->getKey());
            })
            ->orWhere(function ($query) use ($viewer, $owner): void {
                $query
                    ->where('blocker_id', $owner->getKey())
                    ->where('blocked_id', $viewer->getKey());
            })
            ->exists();

        if ($blocked) {
            abort(404);
        }
    }

    private function markPrivateProfileStateWhenHidden(mixed $viewer, User $owner): void
    {
        $visibility = app(\App\Services\ProfileVisibilityService::class)->resolve($owner);

        $this->profileVisibility = $visibility->value;

        if ($visibility === ProfileVisibility::Public) {
            return;
        }

        $isOwner = $viewer instanceof User && $viewer->is($owner);

        if ($isOwner) {
            return;
        }

        $isApprovedFollower = $viewer instanceof User && Follow::query()
            ->where('follower_id', $viewer->getKey())
            ->where('following_id', $owner->getKey())
            ->where('status', 'accepted')
            ->exists();

        if ($isApprovedFollower) {
            return;
        }

        $this->showPrivateProfile = true;
        $this->followStatus = $viewer instanceof User ? $viewer->getFollowStatus($owner) : 'none';
    }

    private function hideOwnerOnlyTabsFromVisitors(mixed $viewer): void
    {
        if ($this->activeTab !== 'scheduled') {
            return;
        }

        if ($viewer instanceof User && $viewer->is($this->profileOwner)) {
            return;
        }

        $this->activeTab = 'posts';
    }

    private function resolveInitialTab(): string
    {
        $requestedTab = request()->query('tab');

        if (is_string($requestedTab) && $requestedTab !== '') {
            return $this->normalizeTab($requestedTab);
        }

        $storedTab = session()->get($this->lastVisitedTabSessionKey());

        return is_string($storedTab) ? $this->normalizeTab($storedTab) : 'posts';
    }

    private function storeLastVisitedTab(): void
    {
        session()->put($this->lastVisitedTabSessionKey(), $this->activeTab);
    }

    private function lastVisitedTabSessionKey(): string
    {
        return sprintf('profiles.%s.active_tab', $this->profileOwner->getKey());
    }

    private function loadHeaderProfileData(User $owner): User
    {
        return User::query()
            ->whereKey($owner->getKey())
            ->with('media')
            ->firstOrFail();
    }

    private function normalizeTab(string $tab): string
    {
        return in_array($tab, self::ALLOWED_TABS, true) ? $tab : 'posts';
    }

    private function sanitizeEditProfileFocusTarget(?string $target): ?string
    {
        $target = trim((string) $target);

        $allowedTargets = [
            'profile_modal_avatar_field',
            'profile_modal_cover_field',
            'profile_modal_name',
            'profile_modal_display_name',
            'profile_modal_bio',
            'profile_modal_headline',
            'profile_modal_location',
            'profile_modal_website',
            'profile_modal_birth_date',
            'profile_modal_pets',
            'profile_modal_following',
        ];

        return in_array($target, $allowedTargets, true) ? $target : null;
    }
};
?>
