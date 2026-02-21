<x-app-layout>
    @php
        $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;

        $canManage = (bool) ($canManageMembers ?? $isAdmin ?? $isOwner ?? false);

        $membersPaginator = $members
            ?? $activeMembers
            ?? \App\Models\GroupMember::query()
                ->where('group_id', $group->id)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhereIn('status', ['active', 'accepted']);
                })
                ->with('user:id,name,username')
                ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'moderator' THEN 3 ELSE 4 END")
                ->orderBy('joined_at')
                ->paginate(20)
                ->withQueryString();

        $membersUrl = Route::has('groups.members')
            ? route('groups.members', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members']);

        $requestsUrl = Route::has('groups.requests')
            ? route('groups.requests', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members', 'request_tab' => 'pending']);
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Group Members</p>
                <h2 class="shell-title text-xl">{{ $group->name }}</h2>
            </div>
            <a href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Group</a>
        </div>
    </x-slot>

    <nav class="shell-card flex flex-wrap gap-2 p-3">
        <a href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Overview</a>
        <a href="{{ $membersUrl }}" class="btn-base btn-primary px-3 py-2 text-sm">Members</a>
        @if ($canManage)
            <a href="{{ $requestsUrl }}" class="btn-base btn-ghost px-3 py-2 text-sm">Requests</a>
        @endif
    </nav>

    <section class="shell-card p-5">
        <div class="space-y-3">
            @forelse ($membersPaginator as $member)
                @php
                    $roleValue = strtolower((string) ($member->role ?? 'member'));
                    $roleClass = match ($roleValue) {
                        'owner' => 'bg-purple-50 text-purple-700 ring-purple-200',
                        'admin' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
                        'moderator' => 'bg-amber-50 text-amber-700 ring-amber-200',
                        default => 'bg-slate-100 text-slate-700 ring-slate-200',
                    };
                @endphp

                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[color:var(--ui-border)] p-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-avatar :src="$member->user?->avatar_url" :name="$member->user?->name" size="sm" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold" style="color: var(--ui-text);">{{ $member->user?->name ?? 'Unknown user' }}</p>
                            <p class="truncate text-xs shell-text-muted">{{ $member->user?->username ? '@'.$member->user->username : 'Member' }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $roleClass }}">
                            {{ \Illuminate\Support\Str::headline($roleValue) }}
                        </span>

                        @if ($canManage && $roleValue !== 'owner')
                            <form method="POST" action="{{ route('groups.members.role', ['group' => $groupRouteKey, 'membership' => $member->id]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <select name="role" class="rounded-md border border-[color:var(--ui-border)] px-2 py-1 text-xs">
                                    <option value="member" @selected($roleValue === 'member')>Member</option>
                                    <option value="moderator" @selected($roleValue === 'moderator')>Moderator</option>
                                    <option value="admin" @selected($roleValue === 'admin')>Admin</option>
                                </select>
                                <button type="submit" class="btn-base btn-ghost px-2.5 py-1.5 text-xs">Save</button>
                            </form>

                            <form method="POST" action="{{ route('groups.members.ban', ['group' => $groupRouteKey, 'membership' => $member->id]) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-base btn-ghost px-2.5 py-1.5 text-xs text-red-600">Ban</button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <x-empty-state title="No Members" description="There are no active members yet." />
            @endforelse
        </div>

        @if ($membersPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $membersPaginator->hasPages())
            <div class="mt-4">{{ $membersPaginator->links() }}</div>
        @endif
    </section>
</x-app-layout>
