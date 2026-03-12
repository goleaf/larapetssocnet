<?php

namespace App\Http\Controllers;

use App\Actions\Groups\CreateGroupAction;
use App\Actions\Groups\DeleteGroupAction;
use App\Actions\Groups\JoinGroupAction;
use App\Actions\Groups\LeaveGroupAction;
use App\Actions\Groups\UpdateGroupAction;
use App\Enums\GroupMemberStatus;
use App\Http\Requests\JoinGroupRequest;
use App\Http\Requests\LeaveGroupRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Services\GroupVisibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Group::class);

        $viewer = $request->user();

        $search = trim((string) $request->string('q'));
        $privacy = (string) $request->string('privacy', 'all');
        $sort = (string) $request->string('sort', 'latest');

        if (! in_array($privacy, ['all', 'public', 'private', 'secret', 'joined', 'owned'], true)) {
            $privacy = 'all';
        }

        if (! in_array($sort, ['latest', 'members', 'name'], true)) {
            $sort = 'latest';
        }

        $groups = Group::paginateIndexResults($viewer, $search, $privacy, $sort);

        $membershipByGroup = Group::membershipMapForUserAndGroups(
            $viewer,
            $groups->getCollection()->pluck('id')
        );

        $owners = $groups->getCollection()
            ->pluck('owner')
            ->filter()
            ->keyBy('id');

        return view('groups.index', [
            'groups' => $groups,
            'owners' => $owners,
            'membershipByGroup' => $membershipByGroup,
            'search' => $search,
            'privacy' => $privacy,
            'sort' => $sort,
        ]);
    }

    public function show(Request $request, Group $group, GroupVisibilityService $visibility): View|RedirectResponse
    {
        $viewer = $request->user()->loadFeedContext();

        $routeValue = $request->route()?->originalParameter('group');
        $routeValue = is_string($routeValue) || is_numeric($routeValue) ? (string) $routeValue : '';
        if ($routeValue !== '' && $routeValue !== $group->slug) {
            $target = route('groups.show', $group->slug);
            $queryString = $request->getQueryString();

            if ($queryString) {
                $target .= '?'.$queryString;
            }

            return redirect($target);
        }

        if ($group->normalizedPrivacy() === 'secret' && Gate::forUser($viewer)->denies('view', $group)) {
            abort(404);
        }

        $this->authorize('view', $group);

        $group->loadMissing('owner:id,name,username');

        $activeTab = (string) $request->string('tab', 'feed');
        if (! in_array($activeTab, ['feed', 'members', 'events', 'about'], true)) {
            $activeTab = 'feed';
        }

        $membership = $group->membershipForUserId((int) $viewer->getKey());
        $isOwner = $group->isOwner($viewer);
        $isMember = $isOwner || $group->isActiveMembership($membership);
        $isPendingMembership = $membership && (string) ($membership->status?->value ?? '') === GroupMemberStatus::Pending->value;
        $memberRole = $membership?->role?->value;
        $isAdmin = $isOwner
            || ($group->isActiveMembership($membership)
                && in_array((string) $memberRole, ['owner', 'admin'], true));

        $canManageMembers = $viewer->can('manageMembers', $group);

        $membersCount = $group->members_count ?? $group->activeMembersCount();
        $postsCount = $group->posts_count ?? $group->calculatePostsCount();
        $eventsCount = $group->eventsCount();

        $feedPosts = null;
        $activeMembers = null;
        $pendingMembers = collect();
        $events = null;
        $sidebarMembers = collect();
        $membersForPage = null;
        $pendingCount = 0;
        $requestTab = (string) $request->string('request_tab')->toString();

        if ($activeTab === 'feed') {
            if ($visibility->canViewGroupPosts($viewer, $group)) {
                $feedPosts = Post::paginateGroupFeed($group, $viewer);
            } else {
                $feedPosts = Post::paginateEmpty();
            }
        }

        if ($canManageMembers) {
            $pendingCount = (int) GroupMember::query()
                ->forGroup((int) $group->getKey())
                ->pending()
                ->count();
        }

        if ($activeTab === 'members') {
            if ($requestTab === 'pending' && $canManageMembers) {
                $pendingMembers = $group->pendingMembers();
            } elseif ($visibility->canViewGroupMembers($viewer, $group)) {
                $activeMembers = $group->paginateActiveMembers();
                $membersForPage = $activeMembers;
            }
        }

        if ($activeTab === 'events') {
            $events = $group->paginateEventsForShow();
        }

        if ($activeMembers instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $sidebarMembers = $activeMembers->getCollection()->take(7);
        }

        if ($sidebarMembers->isEmpty()) {
            $sidebarMembers = GroupMember::query()
                ->forGroup((int) $group->getKey())
                ->active()
                ->with('user:id,name,username')
                ->orderByDesc('role')
                ->orderBy('joined_at')
                ->limit(7)
                ->get();
        }

        return view('groups.show', [
            'viewer' => $viewer,
            'group' => $group,
            'owner' => $group->owner,
            'membership' => $membership,
            'isOwner' => $isOwner,
            'isAdmin' => $isAdmin,
            'isMember' => $isMember,
            'isPendingMembership' => $isPendingMembership,
            'memberRole' => $memberRole,
            'canManageMembers' => $canManageMembers,
            'activeTab' => $activeTab,
            'membersCount' => $membersCount,
            'postsCount' => $postsCount,
            'eventsCount' => $eventsCount,
            'feedPosts' => $feedPosts,
            'activeMembers' => $activeMembers,
            'pendingMembers' => $pendingMembers,
            'events' => $events,
            'privacyLabel' => Str::headline($group->normalizedPrivacy()),
            'privacyValue' => $group->normalizedPrivacy(),
            'speciesLabel' => Str::headline(str_replace(['-', '_'], '', (string) ($group->species ?? $group->species_focus ?? 'all pets'))),
            'groupRouteKey' => $group->slug,
            'sidebarMembers' => $sidebarMembers,
            'membersForPage' => $membersForPage,
            'pendingCount' => $pendingCount,
            'canViewMembers' => $visibility->canViewGroupMembers($viewer, $group),
            'canViewPosts' => $visibility->canViewGroupPosts($viewer, $group),
            'requestTab' => $requestTab,
            'membersUrl' => route('groups.members.index', ['group' => $group->slug]),
            'requestsUrl' => route('groups.requests.index', ['group' => $group->slug, 'tab' => 'members', 'request_tab' => 'pending']),
            'canPost' => $isMember || $isAdmin || $isOwner,
            'canSeePosts' => $visibility->canViewGroupPosts($viewer, $group),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Group::class);

        return view('groups.create', [
            'group' => new Group,
            'selectedPrivacy' => 'public',
        ]);
    }

    public function store(StoreGroupRequest $request, CreateGroupAction $action): RedirectResponse
    {
        $this->authorize('create', Group::class);

        $group = $action->handle(
            $request->user(),
            $request->validated(),
            $request->file('avatar'),
            $request->file('cover')
        );

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'Group created successfully.');
    }

    public function edit(Request $request, Group $group): View|RedirectResponse
    {
        $this->authorize('update', $group);

        $routeValue = $request->route()?->originalParameter('group');
        $routeValue = is_string($routeValue) || is_numeric($routeValue) ? (string) $routeValue : '';
        if ($routeValue !== '' && $routeValue !== $group->slug) {
            $target = route('groups.edit', $group->slug);
            $queryString = $request->getQueryString();

            if ($queryString) {
                $target .= '?'.$queryString;
            }

            return redirect($target);
        }

        return view('groups.edit', [
            'group' => $group,
            'selectedPrivacy' => $group->normalizedPrivacy(),
            'canDelete' => (int) $group->owner_user_id === (int) auth()->id(),
        ]);
    }

    public function update(UpdateGroupRequest $request, Group $group, UpdateGroupAction $action): RedirectResponse
    {
        $this->authorize('update', $group);

        $group = $action->handle(
            $request->user(),
            $group,
            $request->validated(),
            $request->file('avatar'),
            $request->file('cover')
        );

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'Group updated.');
    }

    public function destroy(Group $group, DeleteGroupAction $action): RedirectResponse
    {
        $this->authorize('delete', $group);

        $action->handle($group);

        return redirect()
            ->route('groups.index')
            ->with('status', 'Group deleted.');
    }

    public function join(JoinGroupRequest $request, Group $group, JoinGroupAction $joinGroup, GroupVisibilityService $visibility): RedirectResponse
    {
        $viewer = $request->user();

        if (! $visibility->canJoinGroup($viewer, $group)) {
            return back()->withErrors(['group' => 'You cannot join this group.']);
        }

        try {
            $membership = $joinGroup->handle($viewer, $group, $request->validated('message'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        $status = (string) ($membership->status?->value ?? '') === GroupMemberStatus::Pending->value
            ? 'Join request sent.'
            : 'You joined the group.';

        return back()->with('status', $status);
    }

    public function leave(LeaveGroupRequest $request, Group $group, LeaveGroupAction $leaveGroup): RedirectResponse
    {
        $viewer = $request->user();

        try {
            $left = $leaveGroup->handle($viewer, $group);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        if (! $left) {
            return back()->with('status', 'You are not a member of this group.');
        }

        return back()->with('status', 'You left the group.');
    }
}
