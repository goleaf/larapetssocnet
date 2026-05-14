<?php

namespace App\Http\Controllers;

use App\Enums\GroupMemberStatus;
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
        $user = $request->user();
        $feedThemes = [
            'accessible-soft' => __('feed.themes.accessible_soft'),
            'high-contrast' => __('feed.themes.high_contrast'),
            'minimalist-soothe' => __('feed.themes.minimalist_soothe'),
        ];
        $requestedTheme = $request->query('theme');
        $activeFeedTheme = is_string($requestedTheme) && array_key_exists($requestedTheme, $feedThemes)
            ? $requestedTheme
            : 'accessible-soft';
        $activeFeedThemeLabel = $feedThemes[$activeFeedTheme];

        $ownedPets = $user->pets()
            ->without(['user', 'species', 'breed', 'media', 'tags'])
            ->select(['pets.id', 'pets.user_id', 'pets.name', 'pets.species', 'pets.breed'])
            ->orderBy('pets.name')
            ->get();

        $type = in_array($request->string('type')->toString(), ['text', 'photo', 'video'], true)
            ? $request->string('type')->toString()
            : null;

        $posts = Post::paginateMainFeedResults($user, $type, 15);

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
            compact('posts', 'yourGroups', 'ownedPets'),
            $sidebarData,
            compact('user', 'type', 'feedThemes', 'activeFeedTheme', 'activeFeedThemeLabel'),
        ));
    }
}
