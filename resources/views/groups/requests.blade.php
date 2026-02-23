<x-app-layout>
    @php
        $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
        $statusTab = request()->string('status')->toString();

        if (!in_array($statusTab, ['pending', 'approved', 'rejected'], true)) {
            $statusTab = 'pending';
        }

        $canManage = (bool) ($canManageMembers ?? $isAdmin ?? $isOwner ?? false);

        $baseQuery = \App\Models\GroupMember::query()
            ->where('group_id', $group->id)
            ->with('user:id,name,username')
            ->latest('created_at');

        $pendingCount = (clone $baseQuery)->where('status', 'pending')->count();
        $approvedCount = (clone $baseQuery)->where(function ($query): void {
            $query->whereNull('status')->orWhereIn('status', ['active', 'accepted']);
        })->count();
        $rejectedCount = (clone $baseQuery)->whereIn('status', ['rejected', 'denied', 'banned'])->count();

        $requestsPaginator = match ($statusTab) {
            'approved' => (clone $baseQuery)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhereIn('status', ['active', 'accepted']);
                })
                ->paginate(20)
                ->withQueryString(),
            'rejected' => (clone $baseQuery)
                ->whereIn('status', ['rejected', 'denied', 'banned'])
                ->paginate(20)
                ->withQueryString(),
            default => (clone $baseQuery)
                ->where('status', 'pending')
                ->paginate(20)
                ->withQueryString(),
        };

        $membersUrl = Route::has('groups.members.index')
            ? route('groups.members.index', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members']);

        $requestsBaseUrl = Route::has('groups.requests.index')
            ? route('groups.requests.index', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members', 'request_tab' => 'pending']);

        $tabUrl = static fn (string $status): string => Route::has('groups.requests.index')
            ? route('groups.requests.index', ['group' => $groupRouteKey, 'status' => $status])
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members', 'request_tab' => 'pending', 'status' => $status]);
    @endphp

    <x-slot name="header">
        <x-ui.page-header
            title="{{ $group->name }}"
            subtitle="Membership Requests"
            :breadcrumbs="[
                ['label' => 'Groups', 'href' => route('groups.index')],
                ['label' => $group->name, 'href' => route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed'])],
                ['label' => 'Requests'],
            ]"
        >
            <x-slot name="action">
                <x-ui.button href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" variant="ghost" size="sm">Back to Group</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    @if (!$canManage)
        <x-ui.card>
            <x-ui.empty-state title="No Access" description="Only group moderators and admins can review requests." icon="🔒" />
        </x-ui.card>
    @else
        <nav class="mb-4 flex flex-wrap gap-2">
            <x-ui.button href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" variant="ghost" size="sm">Overview</x-ui.button>
            <x-ui.button href="{{ $membersUrl }}" variant="ghost" size="sm">Members</x-ui.button>
            <x-ui.button href="{{ $requestsBaseUrl }}" variant="primary" size="sm">Requests</x-ui.button>
        </nav>

        <x-ui.card>
            <div class="mb-4 flex flex-wrap gap-2">
                <x-ui.button href="{{ $tabUrl('pending') }}" :variant="$statusTab === 'pending' ? 'primary' : 'ghost'" size="xs">Pending ({{ $pendingCount }})</x-ui.button>
                <x-ui.button href="{{ $tabUrl('approved') }}" :variant="$statusTab === 'approved' ? 'primary' : 'ghost'" size="xs">Approved ({{ $approvedCount }})</x-ui.button>
                <x-ui.button href="{{ $tabUrl('rejected') }}" :variant="$statusTab === 'rejected' ? 'primary' : 'ghost'" size="xs">Rejected ({{ $rejectedCount }})</x-ui.button>
            </div>

            <div class="space-y-3">
                @forelse ($requestsPaginator as $requestMember)
                    @php
                        $statusValue = strtolower((string) ($requestMember->status ?? 'active'));
                        $statusVariant = match ($statusValue) {
                            'pending' => 'warning',
                            'rejected', 'denied', 'banned' => 'danger',
                            default => 'success',
                        };
                    @endphp

                    <x-ui.user-row
                        :name="$requestMember->user?->name ?? 'Unknown user'"
                        :subtitle="$requestMember->user?->username ? '@' . $requestMember->user->username : 'User'"
                    >
                        <x-slot name="action">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge :variant="$statusVariant" size="sm">{{ \Illuminate\Support\Str::headline($statusValue) }}</x-ui.badge>

                                @if ($statusTab === 'pending')
                                    <form method="POST" action="{{ route('groups.requests.approve', ['group' => $groupRouteKey, 'membership' => $requestMember->id]) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="primary" size="xs">Approve</x-ui.button>
                                    </form>

                                    <form method="POST" action="{{ route('groups.requests.reject', ['group' => $groupRouteKey, 'membership' => $requestMember->id]) }}">
                                        @csrf
                                        <x-ui.button type="submit" variant="ghost" size="xs">Reject</x-ui.button>
                                    </form>
                                @endif
                            </div>
                        </x-slot>
                    </x-ui.user-row>
                @empty
                    <x-ui.empty-state title="No Requests" description="No entries in this request state." />
                @endforelse
            </div>

            @if ($requestsPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $requestsPaginator->hasPages())
                <div class="mt-4">
                    <x-ui.pagination :paginator="$requestsPaginator" />
                </div>
            @endif
        </x-ui.card>
    @endif
</x-app-layout>
