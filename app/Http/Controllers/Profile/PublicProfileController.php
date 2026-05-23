<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Jobs\RecordProfileView;
use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Models\Activities\Event;
use App\Models\Analytics\ProfileView;
use App\Models\Analytics\ProfileWrappedSummary;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Models\Pets\Pet;
use App\Services\PetVisibilityService;
use App\Services\ProfileVisibilityService;
use App\Services\ProfileWrappedService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicProfileController extends Controller
{
    public function __construct(
        private readonly PetVisibilityService $petVisibilityService,
        private readonly ProfileVisibilityService $profileVisibilityService,
        private readonly ProfileWrappedService $profileWrappedService,
    ) {}

    public function show(Request $request, User $user): View|RedirectResponse
    {
        $viewer = $request->user() ?: auth()->user();

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

        $profileVisibility = $this->profileVisibilityService->resolve($user);
        $canViewContent = $this->profileVisibilityService->canViewFullProfile($viewer, $user);
        $canViewFollowers = $this->profileVisibilityService->canViewFollowers($viewer, $user);
        $canViewFollowing = $this->profileVisibilityService->canViewFollowing($viewer, $user);
        $canViewLocation = $this->profileVisibilityService->canViewLocation($viewer, $user);
        $followStatus = $viewer ? $viewer->getFollowStatus($user) : 'none';
        $isOwner = $viewer && $viewer->is($user);
        $profileOwnerFollowsViewer = $viewer && ! $isOwner && $user->isFollowing($viewer);
        $canMessage = $this->profileVisibilityService->canMessage($viewer, $user);
        $tab = $this->resolveProfileTab($request, $isOwner);

        if (! $canViewContent) {
            return view('profile.private', [
                'user' => $user,
                'followStatus' => $followStatus,
                'profileVisibility' => $profileVisibility->value,
                'canMessage' => $canMessage,
                'profileOwnerFollowsViewer' => $profileOwnerFollowsViewer,
            ]);
        }

        if ($viewer && ! $isOwner) {
            RecordProfileView::dispatch((int) $user->getKey(), (int) $viewer->getKey());
        }

        $canViewPets = $this->petVisibilityService->canViewPetsForOwner($viewer, $user);
        $pets = collect();
        $featuredPets = collect();

        if ($canViewPets) {
            $featuredPets = $this->profilePetsQuery($user, $viewer)->limit(9)->get();
            $pets = $tab === 'pets'
                ? $featuredPets
                : collect();
        }

        $sidebarPhotos = collect($user->getMedia('photos'))
            ->merge($user->getMedia('avatar'))
            ->merge($user->getMedia('cover'))
            ->take(9)
            ->values();

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

        $scheduledCount = 0;

        if ($viewer && $viewer->is($user)) {
            $scheduledCount = Post::scheduledCountForProfile($user);
        }

        // Badges — always load (up to 8 most recent)
        $badges = $user->badges()->limit(8)->get();

        // Groups tab data
        $canViewGroups = $viewer instanceof User && $viewer->is($user);

        if (! $canViewGroups && $user->groups_visibility === 'everyone') {
            $canViewGroups = true;
        }

        if (! $canViewGroups && $user->groups_visibility === 'followers_only' && $viewer instanceof User) {
            $canViewGroups = $viewer->isFollowing($user);
        }
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
        $activityData = Post::monthlyActivitySummaryForUser($user);
        $profileViewStats = $isOwner
            ? $this->profileViewStats($user)
            : null;

        $profileCompleteness = $isOwner
            ? User::profileCompletenessSummaryFor((int) $user->getKey())
            : [
                'percentage' => 0,
                'missing_items' => [],
            ];
        $profileWrapped = $isOwner
            ? $this->profileWrappedForOwner($user)
            : null;

        if ($isOwner && $profileCompleteness['percentage'] === 100 && ! $user->profile_completed_at) {
            $user->forceFill(['profile_completed_at' => now()])->saveQuietly();
        }

        $profileStats = $this->profileStats($user, [
            'followers' => $canViewFollowers,
            'following' => $canViewFollowing,
            'pets' => $canViewPets,
            'posts' => $canViewContent,
        ]);
        $profileTabCounts = [
            'posts' => (int) ($user->posts_count ?? 0),
            'pets' => $canViewPets ? (int) ($user->pets_count ?? 0) : 0,
            'photos' => (int) ($user->photos_count ?? 0),
            'scheduled' => $isOwner ? $scheduledCount : 0,
        ];

        return view('profile.show', [
            'profileUser' => $user,
            'tab' => $tab,
            'canViewContent' => $canViewContent,
            'canViewFollowers' => $canViewFollowers,
            'canViewFollowing' => $canViewFollowing,
            'canViewPets' => $canViewPets,
            'canViewPhotos' => true,
            'canViewGroups' => $canViewGroups,
            'canViewLikes' => $canViewLikes,
            'canViewLocation' => $canViewLocation,
            'canMessage' => $canMessage,
            'profileStats' => $profileStats,
            'profileTabCounts' => $profileTabCounts,
            'profileVisibility' => $profileVisibility->value,
            'profileVisibilityLabel' => $profileVisibility->label(),
            'profileVisibilityIcon' => $profileVisibility->icon(),
            'pets' => $pets,
            'featuredPets' => $featuredPets,
            'photos' => collect(),
            'galleries' => collect(),
            'sidebarPhotos' => $sidebarPhotos,
            'friendsPreview' => $friendsPreview,
            'posts' => collect(),
            'privatePosts' => collect(),
            'privateCount' => 0,
            'draftPosts' => collect(),
            'draftCount' => 0,
            'scheduledPosts' => collect(),
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
            'profileWrapped' => $profileWrapped,
            'profileCompletenessPercentage' => $profileCompleteness['percentage'],
            'profileCompletenessMissingItems' => $profileCompleteness['missing_items'],
            'followStatus' => $followStatus,
            'isFollowing' => $followStatus === 'following',
            'profileOwnerFollowsViewer' => $profileOwnerFollowsViewer,
            'isBlocked' => $viewer ? $viewer->hasBlocked($user) : false,
            'isBlockedBy' => $viewer ? $viewer->isBlockedBy($user) : false,
            'showEditProfileModal' => (bool) $request->attributes->get('profile_show_edit_modal', false),
            'editProfileFocusTarget' => $request->attributes->get('profile_edit_focus_target'),
        ]);
    }

    public function guestFollowPrompt(Request $request, User $user): RedirectResponse
    {
        if ($user->isUnavailableForProfile()) {
            abort(404);
        }

        if ($request->user() instanceof User) {
            return redirect()->route('profile.show', ['user' => $user]);
        }

        $request->session()->put('url.intended', route('profile.show', ['user' => $user]));

        return redirect()
            ->route('login')
            ->with('status', 'Log in to follow people and see their content.');
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

    private function resolveProfileTab(Request $request, bool $isOwner): string
    {
        $tab = (string) $request->attributes->get('profile_active_tab', $request->string('tab')->toString());
        $allowedTabs = ['posts', 'pets', 'photos', 'about', 'likes', 'groups', 'events', 'contests'];

        if ($isOwner) {
            $allowedTabs[] = 'scheduled';
        }

        return in_array($tab, $allowedTabs, true) ? $tab : 'posts';
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
     * @return array{current: int, previous: int, trend_percent: int|null, trend_direction: string}
     */
    private function profileViewStats(User $user): array
    {
        $timezone = $user->timezone ?: config('app.timezone');
        $today = CarbonImmutable::now($timezone);
        $currentStart = $today->subDays(ProfileView::RECENT_UNIQUE_VIEWER_DAYS - 1)->toDateString();
        $previousStart = $today->subDays((ProfileView::RECENT_UNIQUE_VIEWER_DAYS * 2) - 1)->toDateString();
        $previousEnd = $today->subDays(ProfileView::RECENT_UNIQUE_VIEWER_DAYS)->toDateString();

        $current = ProfileView::uniqueViewerCountForProfile($user, $currentStart, $today->toDateString());
        $previous = ProfileView::uniqueViewerCountForProfile($user, $previousStart, $previousEnd);
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

    private function profileWrappedForOwner(User $user): ?ProfileWrappedSummary
    {
        $timezone = $user->timezone ?: config('app.timezone');
        $now = CarbonImmutable::now($timezone);

        if (! $this->profileWrappedService->isDisplayWindow($now)) {
            return null;
        }

        $year = $this->profileWrappedService->reviewYearFor($now);

        return ProfileWrappedSummary::query()
            ->forUser($user)
            ->forYear($year)
            ->with(['mostEngagedPost:id,body,published_at,created_at'])
            ->first([
                'id',
                'user_id',
                'year',
                'total_posts_published',
                'total_reactions_received',
                'top_reaction_type',
                'top_reaction_count',
                'most_active_month',
                'most_active_month_posts',
                'new_followers_count',
                'pets_added_count',
                'most_engaged_post_id',
                'most_engaged_post_score',
                'share_image_path',
                'generated_at',
                'share_image_generated_at',
            ]);
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
