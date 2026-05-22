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
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
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

    private const ALLOWED_TABS = ['posts', 'pets', 'photos', 'likes', 'groups', 'events', 'contests', 'scheduled'];

    public function mount(string $user): void
    {
        $this->activeTab = $this->normalizeTab((string) request()->query('tab', 'posts'));

        $this->profileOwner = $this->resolveActiveProfileOwner($user);

        $viewer = request()->user();

        $this->abortIfBlocked($viewer, $this->profileOwner);
        $this->redirectRestrictedViewer($viewer);
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
        $viewer = request()->user();

        abort_unless($viewer instanceof User && $viewer->is($this->profileOwner), 403);

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
    }

    public function render(): View
    {
        if ($this->showPrivateProfile) {
            return view('profile.private', [
                'user' => $this->profileOwner,
                'followStatus' => $this->followStatus,
                'profileVisibility' => $this->profileVisibility,
            ])->layout('layouts.livewire-pass-through');
        }

        request()->query->set('tab', $this->activeTab);

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
};
?>
