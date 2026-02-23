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

        $membersUrl = Route::has('groups.members.index')
            ? route('groups.members.index', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members']);

        $requestsUrl = Route::has('groups.requests.index')
            ? route('groups.requests.index', ['group' => $groupRouteKey, 'status' => 'pending'])
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members', 'request_tab' => 'pending']);
    @endphp

    <x-slot name="header">
        <x-ui.page-header
            title="{{ $group->name }}"
            subtitle="Group Members"
            :breadcrumbs="[
                ['label' => 'Groups', 'href' => route('groups.index')],
                ['label' => $group->name, 'href' => route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed'])],
                ['label' => 'Members'],
            ]"
        >
            <x-slot name="action">
                <x-ui.button href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" variant="ghost" size="sm">Back to Group</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <nav class="mb-4 flex flex-wrap gap-2">
        <x-ui.button href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" variant="ghost" size="sm">Overview</x-ui.button>
        <x-ui.button href="{{ $membersUrl }}" variant="primary" size="sm">Members</x-ui.button>
        @if ($canManage)
            <x-ui.button href="{{ $requestsUrl }}" variant="ghost" size="sm">Requests</x-ui.button>
        @endif
    </nav>

    <x-ui.card>
        <div class="space-y-3">
            @forelse ($membersPaginator as $member)
                @php
                    $roleValue = strtolower((string) ($member->role ?? 'member'));
                @endphp

                <x-ui.user-row
                    :name="$member->user?->name ?? 'Unknown user'"
                    :subtitle="$member->user?->username ? '@' . $member->user->username : 'Member'"
                >
                    <x-slot name="action">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.role-badge :role="$roleValue" />

                            @if ($canManage && $roleValue !== 'owner')
                                @if (in_array($roleValue, ['member', 'moderator'], true))
                                    <form method="POST" action="{{ route('groups.members.promote', ['group' => $groupRouteKey, 'membership' => $member->id]) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="ghost" size="xs">Promote</x-ui.button>
                                    </form>
                                @endif

                                @if (in_array($roleValue, ['admin', 'moderator'], true))
                                    <form method="POST" action="{{ route('groups.members.demote', ['group' => $groupRouteKey, 'membership' => $member->id]) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="ghost" size="xs">Demote</x-ui.button>
                                    </form>
                                @endif

                                <form method="POST" action="{{ route('groups.members.remove', ['group' => $groupRouteKey, 'membership' => $member->id]) }}" onsubmit="return confirm('Remove this member from the group?');">
                                    @csrf
                                    @method('DELETE')
                                    <x-ui.button type="submit" variant="danger" size="xs">Remove</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </x-slot>
                </x-ui.user-row>
            @empty
                <x-ui.empty-state title="No Members" description="There are no active members yet." />
            @endforelse
        </div>

        @if ($membersPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $membersPaginator->hasPages())
            <div class="mt-4">
                <x-ui.pagination :paginator="$membersPaginator" />
            </div>
        @endif
    </x-ui.card>
</x-app-layout>
