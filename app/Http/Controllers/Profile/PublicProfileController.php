<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Services\VisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicProfileController extends Controller
{
    public function __construct(private readonly VisibilityService $visibilityService) {}

    public function show(Request $request, User $user): View|RedirectResponse
    {
        $redirect = $request->attributes->get('username_redirect');
        if ($redirect) {
            return redirect()
                ->route('profile.show', ['user' => $redirect->user->username])
                ->setStatusCode(301);
        }

        $rawUsername = (string) $request->attributes->get('username_raw', $user->username);
        if ($rawUsername !== $user->username) {
            return redirect()
                ->route('profile.show', ['user' => $user->username])
                ->setStatusCode(301);
        }

        $viewer = $request->user();

        if ($viewer && ($viewer->hasBlocked($user) || $viewer->isBlockedBy($user))) {
            abort(404);
        }

        $allowedTabs = ['posts', 'pets', 'photos', 'likes', 'groups', 'events', 'contests'];
        $tab = in_array($request->string('tab')->toString(), $allowedTabs, true)
            ? $request->string('tab')->toString()
            : 'posts';

        $canViewContent = $user->canViewPosts($viewer);

        $user->loadCount(['acceptedFollowers as followers_count', 'acceptedFollowing as following_count', 'pets', 'posts']);

        if (! $canViewContent && (bool) $user->is_private) {
            return view('profile.private', [
                'user' => $user,
            ]);
        }

        $canViewPets = $viewer && $viewer->is($user)
            ? true
            : ($canViewContent && ($user->pets_visibility === 'everyone' || ($user->pets_visibility === 'followers_only' && $viewer && $viewer->isFollowing($user))));

        $pets = $tab === 'pets' && $canViewPets
            ? $user->pets()->latest()->get()
            : collect();

        $featuredPets = $canViewPets
            ? $user->pets()->latest()->limit(9)->get()
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
            ? Post::query()
                ->where('user_id', $user->id)
                ->with(['user', 'hashtags'])
                ->published()
                ->visibleTo($viewer)
                ->where('visibility', '!=', Post::VISIBILITY_PRIVATE)
                ->orderByDesc('is_pinned')
                ->latest()
                ->paginate(10)
                ->withQueryString()
            : collect();

        $privatePosts = collect();
        $privateCount = 0;

        if ($tab === 'posts' && $viewer && $viewer->is($user)) {
            $privatePosts = Post::query()
                ->where('user_id', $user->id)
                ->where('visibility', Post::VISIBILITY_PRIVATE)
                ->with(['user', 'hashtags'])
                ->latest()
                ->limit(10)
                ->get()
                ->filter(fn (Post $post): bool => $this->visibilityService->canViewOnProfile($viewer, $post))
                ->values();

            $privateCount = Post::query()
                ->where('user_id', $user->id)
                ->where('visibility', Post::VISIBILITY_PRIVATE)
                ->count();
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
            $eventQuery = fn () => Event::query()
                ->whereHas('attendees', fn ($q) => $q->where('user_id', $user->id)->whereIn('status', ['going', 'interested']))
                ->with('creator');

            $upcomingEvents = $eventQuery()->upcoming()->limit(20)->get();
            $pastEvents = $eventQuery()->past()->limit(5)->get();
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
            ? DB::table('posts')
                ->selectRaw("strftime('%Y-%m', created_at) as month, count(*) as count")
                ->where('user_id', $user->id)
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => [
                    'month' => \Carbon\Carbon::createFromFormat('Y-m', $row->month)->format('M'),
                    'count' => (int) $row->count,
                ])
                ->values()
                ->all()
            : [];

        return view('profile.show', [
            'profileUser' => $user,
            'tab' => $tab,
            'canViewContent' => $canViewContent,
            'pets' => $pets,
            'featuredPets' => $featuredPets,
            'photos' => $photos,
            'galleries' => $galleries,
            'sidebarPhotos' => $sidebarPhotos,
            'friendsPreview' => $friendsPreview,
            'posts' => $posts,
            'privatePosts' => $privatePosts,
            'privateCount' => $privateCount,
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
            'isFollowing' => $viewer ? $viewer->isFollowing($user) : false,
            'isBlocked' => $viewer ? $viewer->hasBlocked($user) : false,
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
