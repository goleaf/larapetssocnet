<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class GroupController extends Controller
{
    /**
     * @var array<string, bool>
     */
    protected static array $columnCache = [];

    public function index(Request $request): View
    {
        $viewer = $request->user();
        $search = trim($request->string('q')->toString());
        $privacy = $request->string('privacy')->toString();
        $sort = $request->string('sort')->toString();

        if (! in_array($privacy, ['all', 'public', 'private', 'secret', 'joined', 'owned'], true)) {
            $privacy = 'all';
        }

        if (! in_array($sort, ['latest', 'members', 'name'], true)) {
            $sort = 'latest';
        }

        $query = Group::query()->select('groups.*');

        $this->applyDiscoveryVisibility($query, $viewer);
        $this->applyIndexFilters($query, $viewer, $privacy, $search);
        $this->applyIndexSort($query, $sort);

        $groups = $query
            ->paginate(12)
            ->withQueryString();

        $membershipByGroup = collect();
        if ($viewer) {
            $membershipByGroup = GroupMember::query()
                ->where('user_id', $viewer->getAuthIdentifier())
                ->whereIn('group_id', $groups->pluck('id'))
                ->get(['group_id', 'status', 'role'])
                ->keyBy('group_id');
        }

        $ownerIds = $groups->map(fn (Group $group): ?int => $this->groupOwnerId($group))
            ->filter()
            ->unique()
            ->values();

        $owners = User::query()
            ->whereIn('id', $ownerIds)
            ->get(['id', 'name', 'username'])
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

    public function show(Request $request, string $group): View
    {
        $groupModel = $this->resolveGroup($group);
        $viewer = $request->user();

        if ($this->hasPolicyFor(Group::class)) {
            $this->authorize('view', $groupModel);
        } else {
            abort_unless($this->canViewGroup($groupModel, $viewer), 403);
        }

        $activeTab = $request->string('tab')->toString();
        if (! in_array($activeTab, ['feed', 'members', 'events', 'about'], true)) {
            $activeTab = 'feed';
        }

        $membership = $viewer
            ? $this->membershipFor($groupModel, (int) $viewer->getAuthIdentifier())
            : null;

        $isOwner = $viewer && $this->isGroupOwner($groupModel, $viewer);
        $isAdmin = $isOwner || ($membership && $this->isMembershipActive($membership) && in_array((string) $membership->role, ['owner', 'admin'], true));
        $isMember = $isOwner || ($membership && $this->isMembershipActive($membership));

        $membersCount = $this->readCounter($groupModel, 'members_count')
            ?? $this->activeMemberCount((int) $groupModel->getKey());
        $postsCount = $this->readCounter($groupModel, 'posts_count')
            ?? $this->groupPostsCount((int) $groupModel->getKey());
        $eventsCount = $this->groupEventsCount((int) $groupModel->getKey());

        $owner = null;
        $ownerId = $this->groupOwnerId($groupModel);
        if ($ownerId) {
            $owner = User::query()->find($ownerId, ['id', 'name', 'username']);
        }

        $feedPosts = null;
        $activeMembers = null;
        $pendingMembers = collect();
        $events = null;

        if ($activeTab === 'feed') {
            $privacy = $this->groupPrivacy($groupModel);
            if ($privacy === 'private' && ! $isMember && ! $isAdmin) {
                $feedPosts = Post::query()
                    ->whereKey(-1)
                    ->paginate(10, ['*'], 'posts_page')
                    ->withQueryString();
            } else {
                $feedPosts = Post::query()
                    ->select('posts.*')
                    ->join('group_posts', 'group_posts.post_id', '=', 'posts.id')
                    ->where('group_posts.group_id', $groupModel->getKey())
                    ->with(['user', 'hashtags'])
                    ->orderByDesc('group_posts.created_at')
                    ->paginate(10, ['posts.*'], 'posts_page')
                    ->withQueryString();
            }
        }

        if ($activeTab === 'members') {
            $activeMembers = GroupMember::query()
                ->where('group_id', $groupModel->getKey())
                ->where(function (Builder $query): void {
                    $query->whereNull('status')->orWhere('status', 'active');
                })
                ->with('user:id,name,username')
                ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END")
                ->orderBy('joined_at')
                ->paginate(20, ['*'], 'members_page')
                ->withQueryString();

            if ($isAdmin) {
                $pendingMembers = GroupMember::query()
                    ->where('group_id', $groupModel->getKey())
                    ->where('status', 'pending')
                    ->with('user:id,name,username')
                    ->latest('created_at')
                    ->get();
            }
        }

        if ($activeTab === 'events') {
            $startColumn = $this->eventStartColumn();

            $events = DB::table('events')
                ->where('group_id', $groupModel->getKey())
                ->orderBy($startColumn)
                ->paginate(12, ['*'], 'events_page')
                ->withQueryString();
        }

        return view('groups.show', [
            'group' => $groupModel,
            'owner' => $owner,
            'membership' => $membership,
            'isOwner' => (bool) $isOwner,
            'isAdmin' => (bool) $isAdmin,
            'isMember' => (bool) $isMember,
            'canManageMembers' => (bool) $isAdmin,
            'activeTab' => $activeTab,
            'membersCount' => $membersCount,
            'postsCount' => $postsCount,
            'eventsCount' => $eventsCount,
            'feedPosts' => $feedPosts,
            'activeMembers' => $activeMembers,
            'pendingMembers' => $pendingMembers,
            'events' => $events,
            'privacyLabel' => Str::headline($this->groupPrivacy($groupModel)),
            'groupRouteKey' => $this->groupRouteKey($groupModel),
        ]);
    }

    public function create(): View
    {
        if ($this->hasPolicyFor(Group::class)) {
            $this->authorize('create', Group::class);
        }

        return view('groups.create', [
            'group' => new Group,
            'selectedPrivacy' => 'public',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->hasPolicyFor(Group::class)) {
            $this->authorize('create', Group::class);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['required', Rule::in(['public', 'private', 'secret'])],
            'cover_image_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $group = DB::transaction(function () use ($request, $validated): Group {
            $group = new Group;

            $payload = [
                'name' => $validated['name'],
                'slug' => $this->generateUniqueGroupSlug($validated['name']),
                'description' => $validated['description'] ?? null,
            ];

            if ($ownerColumn = $this->groupOwnerColumn()) {
                $payload[$ownerColumn] = $request->user()->getAuthIdentifier();
            }

            if ($privacyColumn = $this->groupPrivacyColumn()) {
                $payload[$privacyColumn] = $validated['privacy'];
            }

            if ($isPrivateColumn = $this->groupIsPrivateColumn()) {
                $payload[$isPrivateColumn] = $validated['privacy'] !== 'public';
            }

            if ($rulesColumn = $this->groupRulesColumn()) {
                $payload[$rulesColumn] = $validated['rules'] ?? null;
            }

            if ($coverColumn = $this->groupCoverColumn()) {
                $payload[$coverColumn] = $validated['cover_image_path'] ?? null;
            }

            $group->forceFill($this->filterToExistingColumns('groups', $payload))->save();

            $group->addMember($request->user(), 'owner', 'active');
            $this->syncGroupMembersCount((int) $group->getKey());

            return $group;
        });

        return redirect()
            ->route('groups.show', $this->groupRouteKey($group))
            ->with('status', 'Group created successfully.');
    }

    public function edit(Request $request, string $group): View
    {
        $groupModel = $this->resolveGroup($group);
        $this->authorizeGroupUpdate($request, $groupModel);

        return view('groups.edit', [
            'group' => $groupModel,
            'selectedPrivacy' => $this->groupPrivacy($groupModel),
            'canDelete' => $this->isGroupOwner($groupModel, $request->user()),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);
        $this->authorizeGroupUpdate($request, $groupModel);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'rules' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['required', Rule::in(['public', 'private', 'secret'])],
            'cover_image_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $payload = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ];

        if ($privacyColumn = $this->groupPrivacyColumn()) {
            $payload[$privacyColumn] = $validated['privacy'];
        }

        if ($isPrivateColumn = $this->groupIsPrivateColumn()) {
            $payload[$isPrivateColumn] = $validated['privacy'] !== 'public';
        }

        if ($rulesColumn = $this->groupRulesColumn()) {
            $payload[$rulesColumn] = $validated['rules'] ?? null;
        }

        if ($coverColumn = $this->groupCoverColumn()) {
            $payload[$coverColumn] = $validated['cover_image_path'] ?? null;
        }

        $groupModel->forceFill($this->filterToExistingColumns('groups', $payload))->save();

        return redirect()
            ->route('groups.show', $this->groupRouteKey($groupModel))
            ->with('status', 'Group updated.');
    }

    public function destroy(Request $request, string $group): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);

        if ($this->hasPolicyFor(Group::class)) {
            $this->authorize('delete', $groupModel);
        } else {
            abort_unless($this->isGroupOwner($groupModel, $request->user()), 403);
        }

        $groupModel->delete();

        return redirect()
            ->route('groups.index')
            ->with('status', 'Group deleted.');
    }

    public function join(Request $request, string $group): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);
        $viewer = $request->user();

        $existingMembership = $this->membershipFor($groupModel, (int) $viewer->getAuthIdentifier());
        if ($existingMembership && $this->isMembershipActive($existingMembership)) {
            return back()->with('status', 'You are already a member.');
        }

        if ($existingMembership && $existingMembership->status === 'pending') {
            return back()->with('status', 'Membership request already pending.');
        }

        if ($existingMembership && $existingMembership->status === 'banned') {
            return back()->withErrors(['group' => 'You are banned from this group.']);
        }

        $privacy = $this->groupPrivacy($groupModel);
        if ($privacy === 'secret') {
            return back()->withErrors(['group' => 'This is a secret group and cannot be joined directly.']);
        }

        if ($this->hasPolicyFor(Group::class) && Gate::denies('join', $groupModel)) {
            return back()->withErrors(['group' => 'You cannot join this group.']);
        }

        $status = $privacy === 'public' ? 'active' : 'pending';

        $groupModel->addMember($viewer, 'member', $status);
        $this->syncGroupMembersCount((int) $groupModel->getKey());

        return back()->with('status', $status === 'active'
            ? 'You joined the group.'
            : 'Request sent. An admin will review it.');
    }

    public function leave(Request $request, string $group): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);
        $viewer = $request->user();
        $membership = $this->membershipFor($groupModel, (int) $viewer->getAuthIdentifier());

        if (! $membership) {
            return back()->with('status', 'You are not a member of this group.');
        }

        if ((string) $membership->role === 'owner' || $this->isGroupOwner($groupModel, $viewer)) {
            return back()->withErrors(['group' => 'Group owners cannot leave without transferring ownership first.']);
        }

        $groupModel->removeMember($viewer);
        $this->syncGroupMembersCount((int) $groupModel->getKey());

        return back()->with('status', 'You left the group.');
    }

    public function approveMember(Request $request, string $group, int $membership): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);
        $this->authorizeMemberManagement($request, $groupModel);

        $member = GroupMember::query()
            ->where('group_id', $groupModel->getKey())
            ->whereKey($membership)
            ->firstOrFail();

        if ($member->status !== 'pending') {
            return back()->with('status', 'Membership is not pending.');
        }

        $member->forceFill([
            'status' => 'active',
            'joined_at' => $member->joined_at ?: now(),
        ])->save();

        $this->syncGroupMembersCount((int) $groupModel->getKey());

        return back()->with('status', 'Membership approved.');
    }

    public function rejectMember(Request $request, string $group, int $membership): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);
        $this->authorizeMemberManagement($request, $groupModel);

        $member = GroupMember::query()
            ->where('group_id', $groupModel->getKey())
            ->whereKey($membership)
            ->firstOrFail();

        if ($member->status !== 'pending') {
            return back()->with('status', 'Membership is not pending.');
        }

        $member->delete();
        $this->syncGroupMembersCount((int) $groupModel->getKey());

        return back()->with('status', 'Membership request rejected.');
    }

    public function updateMemberRole(Request $request, string $group, int $membership): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);
        $this->authorizeMemberManagement($request, $groupModel);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['member', 'admin', 'moderator'])],
        ]);

        $target = GroupMember::query()
            ->where('group_id', $groupModel->getKey())
            ->whereKey($membership)
            ->firstOrFail();

        abort_if((string) $target->role === 'owner', 403);

        $actor = $request->user();
        $actorIsOwner = $this->isGroupOwner($groupModel, $actor)
            || ((string) optional($this->membershipFor($groupModel, (int) $actor->getAuthIdentifier()))->role === 'owner');

        if (! $actorIsOwner && ((string) $target->role === 'admin' || $validated['role'] === 'admin')) {
            abort(403);
        }

        $target->update([
            'role' => $validated['role'],
        ]);

        return back()->with('status', 'Member role updated.');
    }

    public function banMember(Request $request, string $group, int $membership): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);
        $this->authorizeMemberManagement($request, $groupModel);

        $target = GroupMember::query()
            ->where('group_id', $groupModel->getKey())
            ->whereKey($membership)
            ->firstOrFail();

        abort_if((string) $target->role === 'owner', 403);

        $wasActive = $this->isMembershipActive($target);
        $target->update([
            'status' => 'banned',
            'role' => 'member',
        ]);

        if ($wasActive) {
            $this->syncGroupMembersCount((int) $groupModel->getKey());
        }

        return back()->with('status', 'Member banned.');
    }

    public function attachPost(Request $request, string $group): RedirectResponse
    {
        $groupModel = $this->resolveGroup($group);
        $viewer = $request->user();
        $membership = $this->membershipFor($groupModel, (int) $viewer->getAuthIdentifier());
        $isOwner = $this->isGroupOwner($groupModel, $viewer);
        $isAdmin = $isOwner
            || ($membership && $this->isMembershipActive($membership) && in_array((string) $membership->role, ['owner', 'admin'], true));

        abort_unless($isOwner || ($membership && $this->isMembershipActive($membership)), 403);

        $validated = $request->validate([
            'post_id' => ['nullable', 'integer', 'exists:posts,id'],
            'body' => ['nullable', 'string', 'max:5000'],
        ]);

        $hasPostId = ! empty($validated['post_id']);
        $hasBody = filled((string) ($validated['body'] ?? null));

        if (! $hasPostId && ! $hasBody) {
            throw ValidationException::withMessages([
                'body' => 'Provide a post body or choose an existing post.',
            ]);
        }

        $postId = DB::transaction(function () use ($hasPostId, $isAdmin, $validated, $viewer): int {
            if ($hasPostId) {
                $post = Post::query()->findOrFail((int) $validated['post_id']);
                abort_unless($isAdmin || (int) $post->user_id === (int) $viewer->getAuthIdentifier(), 403);

                return (int) $post->getKey();
            }

            $postPayload = $this->filterToExistingColumns('posts', [
                'user_id' => $viewer->getAuthIdentifier(),
                'body' => (string) $validated['body'],
                'visibility' => Post::VISIBILITY_PUBLIC,
                'status' => 'published',
                'published_at' => now(),
            ]);

            $post = Post::query()->create($postPayload);

            return (int) $post->getKey();
        });

        $attached = DB::table('group_posts')->insertOrIgnore([
            'group_id' => $groupModel->getKey(),
            'post_id' => $postId,
            'added_by_user_id' => $viewer->getAuthIdentifier(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ((int) $attached > 0) {
            $this->syncGroupPostsCount((int) $groupModel->getKey());

            return back()->with('status', 'Post attached to group.');
        }

        return back()->with('status', 'That post is already attached to this group.');
    }

    protected function resolveGroup(string $group): Group
    {
        $query = Group::query();
        $hasConstraint = false;

        if ($this->hasTableColumn('groups', 'slug')) {
            $query->where('slug', $group);
            $hasConstraint = true;
        }

        if (ctype_digit($group)) {
            $query->orWhere('id', (int) $group);
            $hasConstraint = true;
        }

        if (! $hasConstraint) {
            abort(404);
        }

        return $query->firstOrFail();
    }

    protected function groupRouteKey(Group $group): string|int
    {
        if ($this->hasTableColumn('groups', 'slug') && filled((string) $group->getAttribute('slug'))) {
            return (string) $group->getAttribute('slug');
        }

        return (int) $group->getKey();
    }

    protected function canViewGroup(Group $group, ?Authenticatable $viewer): bool
    {
        $privacy = $this->groupPrivacy($group);

        if ($privacy !== 'secret') {
            return true;
        }

        if (! $viewer) {
            return false;
        }

        if ($this->isGroupOwner($group, $viewer)) {
            return true;
        }

        $membership = $this->membershipFor($group, (int) $viewer->getAuthIdentifier());

        return $membership !== null;
    }

    protected function authorizeGroupUpdate(Request $request, Group $group): void
    {
        if ($this->hasPolicyFor(Group::class)) {
            $this->authorize('update', $group);

            return;
        }

        abort_unless($this->canManageGroup($group, $request->user()), 403);
    }

    protected function authorizeMemberManagement(Request $request, Group $group): void
    {
        if ($this->hasPolicyFor(Group::class)) {
            $this->authorize('moderate', $group);

            return;
        }

        abort_unless($this->canManageGroup($group, $request->user()), 403);
    }

    protected function canManageGroup(Group $group, ?Authenticatable $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if ($this->isGroupOwner($group, $viewer)) {
            return true;
        }

        $membership = $this->membershipFor($group, (int) $viewer->getAuthIdentifier());

        return $membership !== null
            && $this->isMembershipActive($membership)
            && in_array((string) $membership->role, ['owner', 'admin', 'moderator'], true);
    }

    protected function isGroupOwner(Group $group, ?Authenticatable $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        $ownerId = $this->groupOwnerId($group);

        return $ownerId !== null && (int) $ownerId === (int) $viewer->getAuthIdentifier();
    }

    protected function groupOwnerId(Group $group): ?int
    {
        $owner = data_get($group, 'owner_user_id') ?? data_get($group, 'owner_id');

        return $owner ? (int) $owner : null;
    }

    protected function membershipFor(Group $group, int $userId): ?GroupMember
    {
        return GroupMember::query()
            ->where('group_id', $group->getKey())
            ->where('user_id', $userId)
            ->first();
    }

    protected function isMembershipActive(GroupMember $membership): bool
    {
        return $membership->status === null || in_array($membership->status, ['active', 'accepted'], true);
    }

    protected function groupPrivacy(Group $group): string
    {
        if ($privacyColumn = $this->groupPrivacyColumn()) {
            $value = Str::lower((string) ($group->getAttribute($privacyColumn) ?? 'public'));

            if (in_array($value, ['public', 'private', 'secret'], true)) {
                return $value;
            }
        }

        if ($isPrivateColumn = $this->groupIsPrivateColumn()) {
            return (bool) $group->getAttribute($isPrivateColumn) ? 'private' : 'public';
        }

        return 'public';
    }

    protected function groupPrivacyColumn(): ?string
    {
        return $this->firstAvailableColumn('groups', ['privacy']);
    }

    protected function groupIsPrivateColumn(): ?string
    {
        return $this->firstAvailableColumn('groups', ['is_private']);
    }

    protected function groupOwnerColumn(): ?string
    {
        return $this->firstAvailableColumn('groups', ['owner_user_id', 'owner_id']);
    }

    protected function groupRulesColumn(): ?string
    {
        return $this->firstAvailableColumn('groups', ['rules']);
    }

    protected function groupCoverColumn(): ?string
    {
        return $this->firstAvailableColumn('groups', ['cover_image_path', 'cover_photo_path']);
    }

    protected function eventStartColumn(): string
    {
        return $this->firstAvailableColumn('events', ['start_at', 'starts_at']) ?? 'created_at';
    }

    protected function applyDiscoveryVisibility(Builder $query, ?Authenticatable $viewer): void
    {
        $privacyColumn = $this->groupPrivacyColumn();
        $isPrivateColumn = $this->groupIsPrivateColumn();

        $query->where(function (Builder $visibility) use ($isPrivateColumn, $privacyColumn, $viewer): void {
            if ($privacyColumn) {
                $visibility->whereIn("groups.{$privacyColumn}", ['public', 'private'])
                    ->orWhereNull("groups.{$privacyColumn}");
            } elseif (! $isPrivateColumn) {
                $visibility->whereRaw('1 = 1');
            } else {
                $visibility->whereRaw('1 = 1');
            }

            if ($viewer) {
                $visibility->orWhereExists(function ($membershipSubQuery) use ($viewer): void {
                    $membershipSubQuery
                        ->selectRaw('1')
                        ->from('group_members')
                        ->whereColumn('group_members.group_id', 'groups.id')
                        ->where('group_members.user_id', $viewer->getAuthIdentifier());
                });
            }
        });
    }

    protected function applyIndexFilters(Builder $query, ?Authenticatable $viewer, string $privacy, string $search): void
    {
        $privacyColumn = $this->groupPrivacyColumn();
        $isPrivateColumn = $this->groupIsPrivateColumn();
        $ownerColumn = $this->groupOwnerColumn();

        if ($privacy === 'public') {
            if ($privacyColumn) {
                $query->where("groups.{$privacyColumn}", 'public');
            } elseif ($isPrivateColumn) {
                $query->where(function (Builder $subQuery) use ($isPrivateColumn): void {
                    $subQuery->whereNull("groups.{$isPrivateColumn}")->orWhere("groups.{$isPrivateColumn}", false);
                });
            }
        }

        if ($privacy === 'private') {
            if ($privacyColumn) {
                $query->where("groups.{$privacyColumn}", 'private');
            } elseif ($isPrivateColumn) {
                $query->where("groups.{$isPrivateColumn}", true);
            }
        }

        if ($privacy === 'secret' && $privacyColumn) {
            $query->where("groups.{$privacyColumn}", 'secret');
        }

        if ($privacy === 'joined' && $viewer) {
            $query->whereExists(function ($subQuery) use ($viewer): void {
                $subQuery
                    ->selectRaw('1')
                    ->from('group_members')
                    ->whereColumn('group_members.group_id', 'groups.id')
                    ->where('group_members.user_id', $viewer->getAuthIdentifier())
                    ->where(function ($statusQuery): void {
                        $statusQuery->whereNull('group_members.status')->orWhere('group_members.status', 'active');
                    });
            });
        }

        if ($privacy === 'owned' && $viewer && $ownerColumn) {
            $query->where("groups.{$ownerColumn}", $viewer->getAuthIdentifier());
        }

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('groups.name', 'like', "%{$search}%")
                    ->orWhere('groups.description', 'like', "%{$search}%");

                if ($this->hasTableColumn('groups', 'slug')) {
                    $searchQuery->orWhere('groups.slug', 'like', "%{$search}%");
                }
            });
        }
    }

    protected function applyIndexSort(Builder $query, string $sort): void
    {
        if ($sort === 'name') {
            $query->orderBy('groups.name');

            return;
        }

        if ($sort === 'members' && $this->hasTableColumn('groups', 'members_count')) {
            $query->orderByDesc('groups.members_count')
                ->orderByDesc('groups.created_at');

            return;
        }

        $query->latest('groups.created_at');
    }

    protected function generateUniqueGroupSlug(string $name): string
    {
        if (! $this->hasTableColumn('groups', 'slug')) {
            return Str::slug($name) ?: Str::random(8);
        }

        $base = Str::slug($name);
        if ($base === '') {
            $base = 'group';
        }

        $slug = $base;
        $suffix = 1;

        while (Group::query()->where('slug', $slug)->exists()) {
            $suffix++;
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }

    protected function activeMemberCount(int $groupId): int
    {
        return GroupMember::query()
            ->where('group_id', $groupId)
            ->where(function (Builder $query): void {
                $query->whereNull('status')->orWhere('status', 'active');
            })
            ->count();
    }

    protected function groupPostsCount(int $groupId): int
    {
        return (int) DB::table('group_posts')
            ->where('group_id', $groupId)
            ->count();
    }

    protected function groupEventsCount(int $groupId): int
    {
        return (int) DB::table('events')
            ->where('group_id', $groupId)
            ->count();
    }

    protected function syncGroupMembersCount(int $groupId): void
    {
        if (! $this->hasTableColumn('groups', 'members_count')) {
            return;
        }

        DB::table('groups')
            ->where('id', $groupId)
            ->update([
                'members_count' => $this->activeMemberCount($groupId),
            ]);
    }

    protected function syncGroupPostsCount(int $groupId): void
    {
        if (! $this->hasTableColumn('groups', 'posts_count')) {
            return;
        }

        DB::table('groups')
            ->where('id', $groupId)
            ->update([
                'posts_count' => $this->groupPostsCount($groupId),
            ]);
    }

    protected function readCounter(Group $group, string $column): ?int
    {
        if (! $this->hasTableColumn('groups', $column)) {
            return null;
        }

        return (int) ($group->getAttribute($column) ?? 0);
    }

    /**
     * @param  list<string>  $candidates
     */
    protected function firstAvailableColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($this->hasTableColumn($table, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function hasTableColumn(string $table, string $column): bool
    {
        $cacheKey = "{$table}.{$column}";

        if (! array_key_exists($cacheKey, static::$columnCache)) {
            try {
                static::$columnCache[$cacheKey] = Schema::hasColumn($table, $column);
            } catch (Throwable) {
                static::$columnCache[$cacheKey] = false;
            }
        }

        return static::$columnCache[$cacheKey];
    }

    protected function filterToExistingColumns(string $table, array $payload): array
    {
        try {
            $columns = Schema::getColumnListing($table);

            if ($columns === []) {
                return $payload;
            }

            return collect($payload)
                ->only($columns)
                ->all();
        } catch (Throwable) {
            return $payload;
        }
    }

    protected function hasPolicyFor(string $modelClass): bool
    {
        return Gate::getPolicyFor($modelClass) !== null;
    }
}
