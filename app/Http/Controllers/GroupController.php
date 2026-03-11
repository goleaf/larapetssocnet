<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

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

    public function show(Request $request, Group $group): View
    {
        $viewer = $request->user()->loadFeedContext();

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
        $isAdmin = $isOwner
            || ($group->isActiveMembership($membership)
                && in_array((string) $membership?->role, ['owner', 'admin'], true));

        $canManageMembers = $viewer->can('manageMembers', $group);

        $membersCount = $group->members_count ?? $group->activeMembersCount();
        $postsCount = $group->posts_count ?? $group->calculatePostsCount();
        $eventsCount = $group->eventsCount();

        $feedPosts = null;
        $activeMembers = null;
        $pendingMembers = collect();
        $events = null;

        if ($activeTab === 'feed') {
            if (($group->normalizedPrivacy() === 'private' || $group->normalizedPrivacy() === 'secret') && ! $isMember) {
                $feedPosts = Post::paginateEmpty();
            } else {
                $feedPosts = Post::paginateGroupFeed($group);
            }
        }

        if ($activeTab === 'members') {
            $activeMembers = $group->paginateActiveMembers();

            if ($canManageMembers) {
                $pendingMembers = $group->pendingMembers();
            }
        }

        if ($activeTab === 'events') {
            $events = $group->paginateEventsForShow();
        }

        return view('groups.show', [
            'group' => $group,
            'owner' => $group->owner,
            'membership' => $membership,
            'isOwner' => $isOwner,
            'isAdmin' => $isAdmin,
            'isMember' => $isMember,
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
            'groupRouteKey' => $group->slug,
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

    public function store(StoreGroupRequest $request): RedirectResponse
    {
        $this->authorize('create', Group::class);

        $validated = $request->validated();

        $group = Group::create($this->filterGroupPayload([
            'owner_user_id' => $request->user()->getKey(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'privacy' => $validated['privacy'],
            'location' => $validated['location'] ?? null,
            'website' => $validated['website'] ?? null,
            'cover_image_path' => $validated['cover_image_path'] ?? null,
        ]));

        $group->addMember($request->user(), 'owner', 'active');
        $group->syncMembersCount();

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'Group created successfully.');
    }

    public function edit(Group $group): View
    {
        $this->authorize('update', $group);

        return view('groups.edit', [
            'group' => $group,
            'selectedPrivacy' => $group->normalizedPrivacy(),
            'canDelete' => (int) $group->owner_user_id === (int) auth()->id(),
        ]);
    }

    public function update(UpdateGroupRequest $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);

        $validated = $request->validated();

        $group->forceFill($this->filterGroupPayload([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'privacy' => $validated['privacy'],
            'location' => $validated['location'] ?? null,
            'website' => $validated['website'] ?? null,
            'cover_image_path' => $validated['cover_image_path'] ?? null,
        ]))->save();

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'Group updated.');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);

        $group->delete();

        return redirect()
            ->route('groups.index')
            ->with('status', 'Group deleted.');
    }

    public function join(Request $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();
        $membership = $group->membershipForUserId((int) $viewer->getKey());

        if ($membership && $group->isActiveMembership($membership)) {
            return back()->with('status', 'You are already a member.');
        }

        if ($membership && $membership->status === 'pending') {
            return back()->with('status', 'Your join request is already pending.');
        }

        if ($membership && $membership->status === 'banned') {
            return back()->withErrors(['group' => 'You are banned from this group.']);
        }

        if ($group->normalizedPrivacy() === 'secret') {
            return back()->withErrors(['group' => 'Secret groups cannot be joined directly.']);
        }

        if ($membership
            && $membership->status === 'rejected'
            && $membership->updated_at
            && $membership->updated_at->greaterThan(now()->subDays(7))) {
            return back()->withErrors([
                'group' => 'You can request to join again 7 days after a rejection.',
            ]);
        }

        $status = $group->normalizedPrivacy() === 'public' ? 'active' : 'pending';

        if ($membership) {
            $membership->forceFill([
                'role' => 'member',
                'status' => $status,
                'joined_at' => $status === 'active' ? ($membership->joined_at ?: now()) : null,
            ])->save();
        } else {
            $group->memberships()->create([
                'user_id' => $viewer->getKey(),
                'role' => 'member',
                'status' => $status,
                'joined_at' => $status === 'active' ? now() : null,
            ]);
        }

        $group->syncMembersCount();

        return back()->with('status', $status === 'active'
            ? 'You joined the group.'
            : 'Join request sent.');
    }

    public function leave(Request $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();
        $membership = $group->membershipForUserId((int) $viewer->getKey());

        if (! $membership) {
            return back()->with('status', 'You are not a member of this group.');
        }

        if ((int) $group->owner_user_id === (int) $viewer->getKey() || (string) $membership->role === 'owner') {
            return back()->withErrors([
                'group' => 'Group owners cannot leave the group.',
            ]);
        }

        $membership->delete();
        $group->syncMembersCount();

        return back()->with('status', 'You left the group.');
    }

    private function filterGroupPayload(array $payload): array
    {
        try {
            $columns = Schema::getColumnListing('groups');
        } catch (Throwable) {
            return $payload;
        }

        return collect($payload)
            ->only($columns)
            ->all();
    }
}
