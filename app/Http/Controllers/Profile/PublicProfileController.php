<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicProfileController extends Controller
{
    public function show(Request $request, User $user): View
    {
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

        $photos = $tab === 'photos' && $canViewContent
            ? collect($user->getMedia('photos'))
                ->merge($user->getMedia('avatar'))
                ->merge($user->getMedia('cover'))
            : collect();

        return view('profile.show', [
            'profileUser' => $user,
            'tab' => $tab,
            'canViewContent' => $canViewContent,
            'pets' => $pets,
            'photos' => $photos,
            'posts' => collect(),
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
