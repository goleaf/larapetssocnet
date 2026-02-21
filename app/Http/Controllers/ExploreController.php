<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExploreController extends Controller
{
    public function index(Request $request): View
    {
        $viewer = $request->user();
        $type = $request->string('type')->toString();
        $type = in_array($type, ['all', 'photos', 'videos', 'trending'], true) ? $type : 'all';
        $search = trim($request->string('q')->toString());

        $posts = Post::query()
            ->with(['user', 'hashtags'])
            ->published()
            ->where('visibility', Post::VISIBILITY_PUBLIC)
            ->whereHas('author', fn ($authorQuery) => $authorQuery
                ->where('is_private', false)
                ->where('is_banned', false)
            )
            ->notBlockedFor($viewer)
            ->when($type === 'photos', fn ($query) => $query->where('type', Post::TYPE_PHOTO))
            ->when($type === 'videos', fn ($query) => $query->where('type', Post::TYPE_VIDEO))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('body', 'like', '%'.$search.'%')
                        ->orWhere('location', 'like', '%'.$search.'%')
                        ->orWhereHas('hashtags', fn ($hashtagQuery) => $hashtagQuery->where('name', 'like', '%'.strtolower($search).'%'))
                        ->orWhereHas('user', function ($userQuery) use ($search): void {
                            $userQuery
                                ->where('name', 'like', '%'.$search.'%')
                                ->orWhere('username', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when(
                $type === 'trending',
                fn ($query) => $query->orderByRaw('(likes_count + comments_count) desc')->latest(),
                fn ($query) => $query->latest()
            )
            ->paginate(15)
            ->withQueryString();

        return view('explore.index', [
            'posts' => $posts,
            'type' => $type,
            'search' => $search,
        ]);
    }
}
