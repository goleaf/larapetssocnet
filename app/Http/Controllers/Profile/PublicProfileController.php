<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\User;
use App\Services\VisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $allowedTabs = ['posts', 'pets', 'photos', 'likes'];
        $tab = in_array($request->string('tab')->toString(), $allowedTabs, true)
            ? $request->string('tab')->toString()
            : 'posts';

        $canViewContent = $user->canViewPosts($viewer);

        $user->loadCount(['acceptedFollowers as followers_count', 'acceptedFollowing as following_count', 'pets']);

        if (! $canViewContent && (bool) $user->is_private) {
            return view('profile.private', [
                'user' => $user,
            ]);
        }

        $pets = $tab === 'pets' && $canViewContent
            ? $user->pets()->latest()->get()
            : collect();

        $featuredPets = $canViewContent
            ? $user->pets()->latest()->limit(9)->get()
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

        return view('profile.show', [
            'profileUser' => $user,
            'tab' => $tab,
            'canViewContent' => $canViewContent,
            'pets' => $pets,
            'featuredPets' => $featuredPets,
            'photos' => $photos,
            'sidebarPhotos' => $sidebarPhotos,
            'friendsPreview' => $friendsPreview,
            'posts' => $posts,
            'privatePosts' => $privatePosts,
            'privateCount' => $privateCount,
            'likes' => collect(),
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
