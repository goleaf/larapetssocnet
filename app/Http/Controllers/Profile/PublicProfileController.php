<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Activities\Contest;
use App\Models\Activities\ContestEntry;
use App\Models\Activities\Event;
use App\Models\Content\Post;
use App\Models\Identity\User;
use App\Services\PetVisibilityService;
use App\Services\ProfileVisibilityService;
use App\Services\VisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $allowedTabs = ['posts', 'pets', 'photos', 'likes', 'groups', 'events', 'contests'];
        $tab = in_array($request->string('tab')->toString(), $allowedTabs, true)
            ? $request->string('tab')->toString()
            : 'posts';

        $profileVisibility = $this->profileVisibilityService->resolve($user);
        $canViewContent = $this->profileVisibilityService->canViewFullProfile($viewer, $user);
        $followStatus = $viewer ? $viewer->getFollowStatus($user) : 'none';

        $user->loadCount(['acceptedFollowers as followers_count', 'acceptedFollowing as following_count', 'pets', 'posts']);

        if (! $canViewContent) {
            return view('profile.private', [
                'user' => $user,
                'followStatus' => $followStatus,
                'profileVisibility' => $profileVisibility->value,
            ]);
        }

        $canViewPets = $this->petVisibilityService->canViewPetsForOwner($viewer, $user);

        $pets = $tab === 'pets' && $canViewPets
            ? $user->pets()->visibleTo($viewer)->latest()->get()
            : collect();

        $featuredPets = $canViewPets
            ? $user->pets()->visibleTo($viewer)->latest()->limit(9)->get()
            : collect();

        $galleries = $tab === 'photos' && $canViewContent
            ? $user->photoGalleries()
                ->with(['coverMedia', 'media'])
                ->withCount('media')
                ->latest()
                ->get()
            : collect();

        $photos = $tab === 'photos' && $canViewContent
            ? collect($user->getMedia('photos'))
                ->merge($user->getMedia('avatar'))
                ->merge($user->getMedia('cover'))
            : collect();

        $sidebarPhotos = $canViewContent
            ? collect($user->getMedia('photos'))
                ->merge($user->getMedia('avatar'))
                ->merge($user->getMedia('cover'))
                ->take(9)
                ->values()
            : collect();

        $friendsPreview = $canViewContent
            ? $user->acceptedFollowing()
                ->withCount(['acceptedFollowers as followers_count'])
                ->limit(9)
                ->get(['users.id', 'users.name', 'users.username', 'users.avatar_path'])
            : collect();

        $posts = $tab === 'posts' && $canViewContent
            ? Post::paginateProfileTimeline($user, $viewer)
            : collect();

        $privatePosts = collect();
        $privateCount = 0;
        $draftPosts = collect();
        $draftCount = 0;
        $scheduledPosts = collect();
        $scheduledCount = 0;

        if ($tab === 'posts' && $viewer && $viewer->is($user)) {
            $privatePosts = Post::recentPrivateForProfileOwner($user)
                ->filter(fn (Post $post): bool => $this->visibilityService->canViewOnProfile($viewer, $post))
                ->values();

            $privateCount = Post::privateCountForProfile($user);

            $draftPosts = Post::recentDraftsForProfileOwner($user);
            $draftCount = Post::draftCountForProfile($user);

            $scheduledPosts = Post::recentScheduledForProfileOwner($user);
            $scheduledCount = Post::scheduledCountForProfile($user);
        }

        // Badges — always load (up to 8 most recent)
        $badges = $canViewContent
            ? $user->badges()->limit(8)->get()
            : collect();

        // Groups tab data
        $canViewGroups = $viewer && $viewer->is($user)
            ? true
            : ($canViewContent && ($user->groups_visibility === 'everyone' || ($user->groups_visibility === 'followers_only' && $viewer && $viewer->isFollowing($user))));

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
        $isOwner = $viewer && $viewer->is($user);
        if ($viewer && ! $isOwner && $canViewContent) {
            $mutualConnections = $viewer->getMutualFollowers($user);
        }

        // Common groups (visitor only)
        $commonGroups = collect();
        if ($viewer && ! $isOwner && $canViewContent) {
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

        return view('profile.show', [
            'profileUser' => $user,
            'tab' => $tab,
            'canViewContent' => $canViewContent,
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
            'followStatus' => $followStatus,
            'isFollowing' => $followStatus === 'following',
            'isBlocked' => $viewer ? $viewer->hasBlocked($user) : false,
            'isBlockedBy' => $viewer ? $viewer->isBlockedBy($user) : false,
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
