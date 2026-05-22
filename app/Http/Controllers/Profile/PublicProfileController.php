<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Jobs\RecordProfileView;
use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Models\Activities\Event;
use App\Models\Analytics\ProfileView;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetVisibilityService;
use App\Services\ProfileVisibilityService;
use App\Services\VisibilityService;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PublicProfileController extends Controller
{
    public function __construct(
        private readonly VisibilityService $visibilityService,
        private readonly PetVisibilityService $petVisibilityService,
        private readonly ProfileVisibilityService $profileVisibilityService,
    ) {}

    public function show(Request $request, User $user): View|RedirectResponse
    {
        $viewer = $request->user();

        if ($viewer instanceof User) {
            $restrictedRedirect = $this->restrictedViewerRedirect($viewer);

            if ($restrictedRedirect instanceof RedirectResponse) {
                return $restrictedRedirect;
            }
        }

        if ($user->isUnavailableForProfile()) {
            abort(404);
        }

        if ($viewer && $viewer->hasBlockingRelationshipWith($user)) {
            abort(404);
        }

        $redirect = $request->attributes->get('username_redirect');
        if ($redirect) {
            $target = route('profile.show', ['user' => $redirect->user->username]);
            $query = $request->getQueryString();

            if ($query) {
                $target .= '?'.$query;
            }

            return redirect()->to($target, 301);
        }

        $rawUsername = (string) $request->attributes->get('username_raw', $user->username);
        if ($rawUsername !== $user->username) {
            $target = route('profile.show', ['user' => $user->username]);
            $query = $request->getQueryString();

            if ($query) {
                $target .= '?'.$query;
            }

            return redirect()->to($target, 301);
        }

        $user = $this->profileSurfaceUser($user);

        $allowedTabs = ['posts', 'pets', 'photos', 'likes', 'groups', 'events', 'contests', 'scheduled'];
        $tab = in_array($request->string('tab')->toString(), $allowedTabs, true)
            ? $request->string('tab')->toString()
            : 'posts';

        $profileVisibility = $this->profileVisibilityService->resolve($user);
        $canViewContent = $this->profileVisibilityService->canViewFullProfile($viewer, $user);
        $canViewFollowers = $this->profileVisibilityService->canViewFollowers($viewer, $user);
        $canViewFollowing = $this->profileVisibilityService->canViewFollowing($viewer, $user);
        $canViewLocation = $this->profileVisibilityService->canViewLocation($viewer, $user);
        $canMessage = $this->profileVisibilityService->canMessage($viewer, $user);
        $followStatus = $viewer ? $viewer->getFollowStatus($user) : 'none';
        $isOwner = $viewer && $viewer->is($user);

        if (! $canViewContent) {
            return view('profile.private', [
                'user' => $user,
                'followStatus' => $followStatus,
                'profileVisibility' => $profileVisibility->value,
                'canMessage' => $canMessage,
            ]);
        }

        if ($viewer && ! $isOwner) {
            RecordProfileView::dispatch((int) $user->getKey(), (int) $viewer->getKey());
        }

        $canViewPets = $this->petVisibilityService->canViewPetsForOwner($viewer, $user);
        $canViewPhotos = $canViewContent;

        $pets = $tab === 'pets' && $canViewPets
            ? $this->profilePetsQuery($user, $viewer)->get()
            : collect();

        $featuredPets = collect();
        if ($canViewPets) {
            $featuredPets = $tab === 'pets'
                ? $pets->take(9)->values()
                : $this->profilePetsQuery($user, $viewer)->limit(9)->get();
        }

        $galleries = $tab === 'photos' && $canViewPhotos
            ? $user->photoGalleries()
                ->with(['coverMedia', 'media'])
                ->withCount('media')
                ->latest()
                ->get()
            : collect();

        $photos = $tab === 'photos' && $canViewPhotos
            ? collect($user->getMedia('photos'))
                ->merge($user->getMedia('avatar'))
                ->merge($user->getMedia('cover'))
            : collect();

        $sidebarPhotos = $canViewPhotos
            ? collect($user->getMedia('photos'))
                ->merge($user->getMedia('avatar'))
                ->merge($user->getMedia('cover'))
                ->take(9)
                ->values()
            : collect();

        $friendsPreview = collect();

        if ($canViewFollowing) {
            $friendsPreviewQuery = User::query()
                ->whereIn('users.id', $user->acceptedFollowing()->select('users.id'));

            User::applyAvailableForProfiles($friendsPreviewQuery);

            if (! $this->viewerCanBypassThirdPartyPrivacy($viewer, $user)) {
                (new User)->scopeVisibleTo($friendsPreviewQuery, $viewer);
            }

            $friendsPreview = $friendsPreviewQuery
                ->limit(9)
                ->get(['users.id', 'users.name', 'users.username', 'users.avatar_path']);
        }

        $posts = $tab === 'posts' && $canViewContent
            ? Post::paginateProfileTimeline($user, $viewer)
            : collect();

        $privatePosts = collect();
        $privateCount = 0;
        $draftPosts = collect();
        $draftCount = 0;
        $scheduledPosts = collect();
        $scheduledCount = 0;

        if ($viewer && $viewer->is($user)) {
            $scheduledCount = Post::scheduledCountForProfile($user);

            if (in_array($tab, ['posts', 'scheduled'], true)) {
                $privatePosts = Post::recentPrivateForProfileOwner($user)
                    ->filter(fn (Post $post): bool => $this->visibilityService->canViewOnProfile($viewer, $post))
                    ->values();

                $privateCount = Post::privateCountForProfile($user);

                $draftPosts = Post::recentDraftsForProfileOwner($user);
                $draftCount = Post::draftCountForProfile($user);

                $scheduledPosts = Post::recentScheduledForProfileOwner($user);
            }
        }

        // Badges — always load (up to 8 most recent)
        $badges = $canViewContent
            ? $user->badges()->limit(8)->get()
            : collect();

        // Groups tab data
        $canViewGroups = $viewer && $viewer->is($user)
            ? true
            : ($canViewContent && ($user->groups_visibility === 'everyone' || ($user->groups_visibility === 'followers_only' && $viewer && $viewer->isFollowing($user))));
        $canViewLikes = $viewer && ($viewer->is($user) || $viewer->hasAnyRole(['admin', 'moderator']));

        $groups = $tab === 'groups' && $canViewGroups
            ? $user->groups()->withCount('members')->get()
            : collect();

        // Events tab data — split into upcoming and past
        // Uses Event::query with whereHas to avoid pivot column compatibility issues
        $upcomingEvents = collect();
        $pastEvents = collect();
        if ($tab === 'events' && $canViewContent) {
            $eventBuckets = Event::attendeeBucketsForProfile($user);
            $upcomingEvents = $eventBuckets['upcoming'];
            $pastEvents = $eventBuckets['past'];
        }

        // Contests tab data — entries + organized
        $contestEntries = collect();
        $organizedContests = collect();
        if ($tab === 'contests' && $canViewContent) {
            $contestEntries = ContestEntry::query()
                ->where('user_id', $user->id)
                ->with('contest')
                ->latest()
                ->get();

            $organizedContests = Contest::query()
                ->where('organizer_user_id', $user->id)
                ->visible()
                ->latest()
                ->get();
        }

        // Mutual connections (visitor only)
        $mutualConnections = collect();
        if ($viewer && ! $isOwner && $canViewFollowing) {
            $mutualConnections = $viewer->getMutualFollowers($user);
        }

        // Common groups (visitor only)
        $commonGroups = collect();
        if ($viewer && ! $isOwner && $canViewGroups) {
            $viewerGroupIds = $viewer->groups()->pluck('groups.id');
            $commonGroups = $user->groups()
                ->whereIn('groups.id', $viewerGroupIds)
                ->withCount('members')
                ->limit(5)
                ->get();
        }

        // Activity chart — posts per month for last 6 months
        $activityData = $canViewContent
            ? Post::monthlyActivitySummaryForUser($user)
            : [];
        $profileViewStats = $isOwner
            ? $this->profileViewStats($user)
            : null;

        $profileCompleteness = $isOwner
            ? User::profileCompletenessSummaryFor((int) $user->getKey())
            : [
                'percentage' => 0,
                'missing_items' => [],
            ];

        if ($isOwner && $profileCompleteness['percentage'] === 100 && ! $user->profile_completed_at) {
            $user->forceFill(['profile_completed_at' => now()])->saveQuietly();
        }

        $profileStats = $this->profileStats($user, [
            'followers' => $canViewFollowers,
            'following' => $canViewFollowing,
            'pets' => $canViewPets,
            'posts' => $canViewContent,
        ]);

        $followersModalPreview = $canViewFollowers
            ? $this->followersModalPreview($user, $viewer)
            : collect();
        $followingModalPreview = $canViewFollowing
            ? $this->followingModalPreview($user, $viewer)
            : collect();

        return view('profile.show', [
            'profileUser' => $user,
            'tab' => $tab,
            'canViewContent' => $canViewContent,
            'canViewFollowers' => $canViewFollowers,
            'canViewFollowing' => $canViewFollowing,
            'canViewPets' => $canViewPets,
            'canViewPhotos' => $canViewPhotos,
            'canViewGroups' => $canViewGroups,
            'canViewLikes' => $canViewLikes,
            'canViewLocation' => $canViewLocation,
            'canMessage' => $canMessage,
            'profileStats' => $profileStats,
            'followersModalPreview' => $followersModalPreview,
            'followingModalPreview' => $followingModalPreview,
            'profileVisibility' => $profileVisibility->value,
            'profileVisibilityLabel' => $profileVisibility->label(),
            'profileVisibilityIcon' => $profileVisibility->icon(),
            'pets' => $pets,
            'featuredPets' => $featuredPets,
            'photos' => $photos,
            'galleries' => $galleries,
            'sidebarPhotos' => $sidebarPhotos,
            'friendsPreview' => $friendsPreview,
            'posts' => $posts,
            'privatePosts' => $privatePosts,
            'privateCount' => $privateCount,
            'draftPosts' => $draftPosts,
            'draftCount' => $draftCount,
            'scheduledPosts' => $scheduledPosts,
            'scheduledCount' => $scheduledCount,
            'likes' => collect(),
            'badges' => $badges,
            'groups' => $groups,
            'upcomingEvents' => $upcomingEvents,
            'pastEvents' => $pastEvents,
            'contestEntries' => $contestEntries,
            'organizedContests' => $organizedContests,
            'mutualConnections' => $mutualConnections,
            'commonGroups' => $commonGroups,
            'activityData' => $activityData,
            'profileViewStats' => $profileViewStats,
            'profileCompletenessPercentage' => $profileCompleteness['percentage'],
            'profileCompletenessMissingItems' => $profileCompleteness['missing_items'],
            'followStatus' => $followStatus,
            'isFollowing' => $followStatus === 'following',
            'isBlocked' => $viewer ? $viewer->hasBlocked($user) : false,
            'isBlockedBy' => $viewer ? $viewer->isBlockedBy($user) : false,
        ]);
    }

    private function profileSurfaceUser(User $user): User
    {
        return User::query()
            ->whereKey($user->getKey())
            ->with('media')
            ->firstOrFail();
    }

    /**
     * @return HasMany<Pet, User>
     */
    private function profilePetsQuery(User $user, ?User $viewer): HasMany
    {
        return $user->pets()
            ->visibleTo($viewer)
            ->without(['user', 'species', 'breed', 'tags'])
            ->with('media')
            ->latest('pets.created_at');
    }

    private function restrictedViewerRedirect(User $viewer): ?RedirectResponse
    {
        if ((bool) $viewer->is_banned) {
            return redirect()->route('banned');
        }

        if ($viewer->hasPendingDeletion()) {
            return redirect()->route('account.deletion-pending');
        }

        if ($viewer->isDeactivated()) {
            return redirect()->route('account.reactivation');
        }

        if ($viewer->isSuspended()) {
            return redirect()->route('account.suspended');
        }

        return null;
    }

    /**
     * @param  array{followers: bool, following: bool, pets: bool, posts: bool}  $permissions
     * @return array{followers: int|null, following: int|null, pets: int|null, posts: int|null}
     */
    private function profileStats(User $user, array $permissions): array
    {
        return [
            'followers' => $permissions['followers']
                ? (int) ($user->followers_count ?? 0)
                : null,
            'following' => $permissions['following']
                ? (int) ($user->following_count ?? 0)
                : null,
            'pets' => $permissions['pets']
                ? (int) ($user->pets_count ?? 0)
                : null,
            'posts' => $permissions['posts']
                ? (int) ($user->posts_count ?? 0)
                : null,
        ];
    }

    private function viewerCanBypassThirdPartyPrivacy(?User $viewer, User $profileOwner): bool
    {
        return $viewer instanceof User
            && ($viewer->is($profileOwner) || $viewer->hasAnyRole(['admin', 'moderator']));
    }

    /**
     * @return Collection<int, User>
     */
    private function followersModalPreview(User $user, ?User $viewer): Collection
    {
        return $user->acceptedFollowers()
            ->active()
            ->notBlockedFor($viewer)
            ->with('media')
            ->orderBy('users.name')
            ->limit(12)
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function followingModalPreview(User $user, ?User $viewer): Collection
    {
        return $user->acceptedFollowing()
            ->active()
            ->notBlockedFor($viewer)
            ->with('media')
            ->orderBy('users.name')
            ->limit(12)
            ->get();
    }

    /**
     * @return array{current: int, previous: int, trend_percent: int|null, trend_direction: string}
     */
    private function profileViewStats(User $user): array
    {
        $today = now()->toDateString();
        $currentStart = now()->subDays(29)->toDateString();
        $previousStart = now()->subDays(59)->toDateString();
        $previousEnd = now()->subDays(30)->toDateString();

        $current = (int) ProfileView::query()
            ->where('profile_user_id', $user->getKey())
            ->whereBetween('viewed_on', [$currentStart, $today])
            ->distinct('viewer_user_id')
            ->count('viewer_user_id');

        $previous = (int) ProfileView::query()
            ->where('profile_user_id', $user->getKey())
            ->whereBetween('viewed_on', [$previousStart, $previousEnd])
            ->distinct('viewer_user_id')
            ->count('viewer_user_id');

        $trendPercent = $previous > 0
            ? (int) round((($current - $previous) / $previous) * 100)
            : ($current > 0 ? 100 : null);

        return [
            'current' => $current,
            'previous' => $previous,
            'trend_percent' => $trendPercent,
            'trend_direction' => ($trendPercent ?? 0) >= 0 ? 'up' : 'down',
        ];
    }

    public function followers(User $user): View
    {
        $followers = $user->followers()
            ->withCount(['followers', 'following'])
            ->orderBy('users.name')
            ->paginate(20);

        return view('profile.followers', [
            'profileUser' => $user,
            'followers' => $followers,
        ]);
    }

    public function following(User $user): View
    {
        $following = $user->following()
            ->withCount(['followers', 'following'])
            ->orderBy('users.name')
            ->paginate(20);

        return view('profile.following', [
            'profileUser' => $user,
            'following' => $following,
        ]);
    }
}
