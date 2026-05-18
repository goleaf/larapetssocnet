<?php

namespace App\Http\Controllers\Feed;

use App\Enums\GroupMemberStatus;
use App\Http\Controllers\Controller;
use App\Models\Content\Post;
use App\Services\FeedService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function __construct(private FeedService $feed) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $ownedPets = $user->pets()
            ->without(['user', 'species', 'breed', 'media', 'tags'])
            ->select(['pets.id', 'pets.user_id', 'pets.name', 'pets.species', 'pets.breed'])
            ->orderBy('pets.name')
            ->get();

        $type = in_array($request->string('type')->toString(), ['text', 'photo', 'video'], true)
            ? $request->string('type')->toString()
            : null;

        $source = in_array($request->string('source')->toString(), ['people', 'pets'], true)
            ? $request->string('source')->toString()
            : null;

        $posts = Post::paginateMainFeedResults($user, $type, 15, $source);

        $sidebarData = $this->feed->getSidebarData($user);

        $yourGroups = $user->groups()
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('group_members.status')
                    ->orWhereIn('group_members.status', GroupMemberStatus::activeValues());
            })
            ->orderByDesc('groups.members_count')
            ->limit(6)
            ->get();

        return view('feed.index', array_merge(
            ['posts' => $posts, 'yourGroups' => $yourGroups, 'ownedPets' => $ownedPets],
            $sidebarData,
            ['user' => $user, 'type' => $type, 'source' => $source],
        ));
    }
}
