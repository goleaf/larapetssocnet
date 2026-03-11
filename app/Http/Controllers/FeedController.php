<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\FeedService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request): View
    {
        $user = $request->user()->loadMissing('pets:id,user_id,name');
        $viewerId = (int) $user->getKey();

        $type = in_array($request->string('type')->toString(), ['text', 'photo', 'video'], true)
            ? $request->string('type')->toString()
            : null;

        $posts = Post::query()
            ->forFeed($viewerId)
            ->with([
                'user',
                'author',
                'pet',
                'media',
                'tags',
            ])
            ->withCount([
                'likes',
                'comments',
            ])
            ->withExists([
                'likes' => fn (Builder $likeQuery): Builder => $likeQuery
                    ->where('likes.user_id', $viewerId),
                'likes as liked_by_viewer' => fn (Builder $likeQuery): Builder => $likeQuery
                    ->where('likes.user_id', $viewerId),
            ])
            ->when($type !== null, fn (Builder $query): Builder => $query->byType($type))
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->cursorPaginate(15)
            ->withQueryString();

        $sidebarData = $this->feed->getSidebarData($user);

        $yourGroups = $user->groups()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('group_members.status')
                    ->orWhereIn('group_members.status', ['active', 'accepted']);
            })
            ->orderByDesc('groups.members_count')
            ->limit(6)
            ->get();

        return view('feed.index', array_merge(
            compact('posts', 'yourGroups'),
            $sidebarData,
            compact('user', 'type'),
        ));
    }
}
