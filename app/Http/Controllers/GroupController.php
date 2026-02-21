<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGroupRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $viewerId = (int) $viewer->getKey();

        $search = trim((string) $request->string('q'));
        $privacy = (string) $request->string('privacy', 'all');
        $sort = (string) $request->string('sort', 'latest');

        if (! in_array($privacy, ['all', 'public', 'private', 'secret', 'joined', 'owned'], true)) {
            $privacy = 'all';
        }

        if (! in_array($sort, ['latest', 'members', 'name'], true)) {
            $sort = 'latest';
        }

        $groupsQuery = Group::query()
            ->with('owner:id,name,username')
            ->where(function (Builder $visibilityQuery) use ($viewerId): void {
                $visibilityQuery
                    ->where(function (Builder $discoverableQuery): void {
                        $discoverableQuery
                            ->where(function (Builder $privacyQuery): void {
                                $privacyQuery
                                    ->whereNull('privacy')
                                    ->orWhere('privacy', '!=', 'secret');
                            })
                            ->where(function (Builder $typeQuery): void {
                                $typeQuery
                                    ->whereNull('type')
                                    ->orWhere('type', '!=', 'secret');
                            });
                    })
                    ->orWhere('owner_user_id', $viewerId)
                    ->orWhereExists(function ($membershipSubQuery) use ($viewerId): void {
                        $membershipSubQuery
                            ->selectRaw('1')
                            ->from('group_members')
                            ->whereColumn('group_members.group_id', 'groups.id')
                            ->where('group_members.user_id', $viewerId)
                            ->where(function ($statusQuery): void {
                                $statusQuery
                                    ->whereNull('group_members.status')
                                    ->orWhereIn('group_members.status', ['active', 'accepted']);
                            });
                    });
            });

        if (in_array($privacy, ['public', 'private', 'secret'], true)) {
            $groupsQuery->where(function (Builder $privacyQuery) use ($privacy): void {
                $privacyQuery
                    ->where('privacy', $privacy)
                    ->orWhere(function (Builder $fallbackTypeQuery) use ($privacy): void {
                        $fallbackTypeQuery
                            ->whereNull('privacy')
                            ->where('type', $privacy);
                    });
            });
        }

        if ($privacy === 'joined') {
            $groupsQuery->whereExists(function ($membershipSubQuery) use ($viewerId): void {
                $membershipSubQuery
                    ->selectRaw('1')
                    ->from('group_members')
                    ->whereColumn('group_members.group_id', 'groups.id')
                    ->where('group_members.user_id', $viewerId)
                    ->where(function ($statusQuery): void {
                        $statusQuery
                            ->whereNull('group_members.status')
                            ->orWhereIn('group_members.status', ['active', 'accepted']);
                    });
            });
        }

        if ($privacy === 'owned') {
            $groupsQuery->where('owner_user_id', $viewerId);
        }

        if ($search !== '') {
            $groupsQuery->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('groups.name', 'like', "%{$search}%")
                    ->orWhere('groups.description', 'like', "%{$search}%")
                    ->orWhere('groups.slug', 'like', "%{$search}%");
            });
        }

        if ($sort === 'name') {
            $groupsQuery->orderBy('groups.name');
        } elseif ($sort === 'members') {
            $groupsQuery->orderByDesc('groups.members_count')
                ->orderByDesc('groups.created_at');
        } else {
            $groupsQuery->latest('groups.created_at');
        }

        $groups = $groupsQuery
            ->paginate(12)
            ->withQueryString();

        $membershipByGroup = GroupMember::query()
            ->where('user_id', $viewerId)
            ->whereIn('group_id', $groups->pluck('id'))
            ->get(['id', 'group_id', 'status', 'role', 'updated_at'])
            ->keyBy('group_id');

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
        $viewer = $request->user();

        if ($this->privacy($group) === 'secret' && Gate::forUser($viewer)->denies('view', $group)) {
            abort(404);
        }

        $this->authorize('view', $group);

        $group->loadMissing('owner:id,name,username');

        $activeTab = (string) $request->string('tab', 'feed');
        if (! in_array($activeTab, ['feed', 'members', 'events', 'about'], true)) {
            $activeTab = 'feed';
        }

        $membership = $this->membershipForUser($group, (int) $viewer->getKey());
        $isOwner = (int) $group->owner_user_id === (int) $viewer->getKey();
        $isMember = $isOwner || $this->isActiveMembership($membership);
        $isAdmin = $isOwner
            || ($this->isActiveMembership($membership)
                && in_array((string) $membership?->role, ['owner', 'admin'], true));

        $canManageMembers = $viewer->can('manageMembers', $group);

        $membersCount = $group->members_count ?? $this->activeMembersCount($group);
        $postsCount = $group->posts_count ?? $this->groupPostCount((int) $group->getKey());
        $eventsCount = $group->events()->count();

        $feedPosts = null;
        $activeMembers = null;
        $pendingMembers = collect();
        $events = null;

        if ($activeTab === 'feed') {
            if (($this->privacy($group) === 'private' || $this->privacy($group) === 'secret') && ! $isMember) {
                $feedPosts = Post::query()
                    ->whereKey(-1)
                    ->paginate(10, ['*'], 'posts_page')
                    ->withQueryString();
            } else {
                $feedPosts = Post::query()
                    ->where(function (Builder $query) use ($group): void {
                        $query
                            ->where('posts.group_id', $group->getKey())
                            ->orWhereIn('posts.id', function ($pivotSubQuery) use ($group): void {
                                $pivotSubQuery
                                    ->select('group_posts.post_id')
                                    ->from('group_posts')
                                    ->where('group_posts.group_id', $group->getKey());
                            });
                    })
                    ->with([
                        'user:id,name,username,avatar_path',
                        'hashtags:id,name,slug',
                    ])
                    ->latest('posts.created_at')
                    ->paginate(10, ['posts.*'], 'posts_page')
                    ->withQueryString();
            }
        }

        if ($activeTab === 'members') {
            $activeMembers = GroupMember::query()
                ->where('group_id', $group->getKey())
                ->where(function (Builder $statusQuery): void {
                    $statusQuery
                        ->whereNull('status')
                        ->orWhereIn('status', ['active', 'accepted']);
                })
                ->with('user:id,name,username')
                ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'moderator' THEN 3 ELSE 4 END")
                ->orderBy('joined_at')
                ->paginate(20, ['*'], 'members_page')
                ->withQueryString();

            if ($canManageMembers) {
                $pendingMembers = GroupMember::query()
                    ->where('group_id', $group->getKey())
                    ->where('status', 'pending')
                    ->with('user:id,name,username')
                    ->latest('created_at')
                    ->get();
            }
        }

        if ($activeTab === 'events') {
            $events = $group->events()
                ->orderBy('start_at')
                ->paginate(12, ['*'], 'events_page')
                ->withQueryString();
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
            'privacyLabel' => Str::headline($this->privacy($group)),
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

        $group = Group::query()->create($this->filterGroupPayload([
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
        $this->syncMembersCount($group);

        return redirect()
            ->route('groups.show', $group)
            ->with('status', 'Group created successfully.');
    }

    public function edit(Group $group): View
    {
        $this->authorize('update', $group);

        return view('groups.edit', [
            'group' => $group,
            'selectedPrivacy' => $this->privacy($group),
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
        $membership = $this->membershipForUser($group, (int) $viewer->getKey());

        if ($membership && $this->isActiveMembership($membership)) {
            return back()->with('status', 'You are already a member.');
        }

        if ($membership && $membership->status === 'pending') {
            return back()->with('status', 'Your join request is already pending.');
        }

        if ($membership && $membership->status === 'banned') {
            return back()->withErrors(['group' => 'You are banned from this group.']);
        }

        if ($this->privacy($group) === 'secret') {
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

        $status = $this->privacy($group) === 'public' ? 'active' : 'pending';

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

        $this->syncMembersCount($group);

        return back()->with('status', $status === 'active'
            ? 'You joined the group.'
            : 'Join request sent.');
    }

    public function leave(Request $request, Group $group): RedirectResponse
    {
        $viewer = $request->user();
        $membership = $this->membershipForUser($group, (int) $viewer->getKey());

        if (! $membership) {
            return back()->with('status', 'You are not a member of this group.');
        }

        if ((int) $group->owner_user_id === (int) $viewer->getKey() || (string) $membership->role === 'owner') {
            return back()->withErrors([
                'group' => 'Group owners cannot leave the group.',
            ]);
        }

        $membership->delete();
        $this->syncMembersCount($group);

        return back()->with('status', 'You left the group.');
    }

    private function privacy(Group $group): string
    {
        $privacy = strtolower((string) ($group->privacy ?: $group->type ?: 'public'));

        return in_array($privacy, ['public', 'private', 'secret'], true)
            ? $privacy
            : 'public';
    }

    private function membershipForUser(Group $group, int $userId): ?GroupMember
    {
        return GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $userId)
            ->first();
    }

    private function isActiveMembership(?GroupMember $membership): bool
    {
        if (! $membership) {
            return false;
        }

        return $membership->status === null
            || in_array((string) $membership->status, ['active', 'accepted'], true);
    }

    private function activeMembersCount(Group $group): int
    {
        return (int) $group->memberships()
            ->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereNull('status')
                    ->orWhereIn('status', ['active', 'accepted']);
            })
            ->count();
    }

    private function syncMembersCount(Group $group): void
    {
        if (! $this->hasColumn('groups', 'members_count')) {
            return;
        }

        $group->forceFill([
            'members_count' => $this->activeMembersCount($group),
        ])->save();
    }

    private function groupPostCount(int $groupId): int
    {
        $postIds = collect();

        if ($this->hasColumn('posts', 'group_id')) {
            $postIds = $postIds->merge(
                DB::table('posts')->where('group_id', $groupId)->pluck('id')
            );
        }

        if (Schema::hasTable('group_posts')) {
            $postIds = $postIds->merge(
                DB::table('group_posts')->where('group_id', $groupId)->pluck('post_id')
            );
        }

        return $postIds->unique()->count();
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

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
