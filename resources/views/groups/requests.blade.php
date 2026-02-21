<x-app-layout>
    @php
        $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
        $statusTab = request()->string('status')->toString();

        if (! in_array($statusTab, ['pending', 'approved', 'rejected'], true)) {
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

        $membersUrl = Route::has('groups.members')
            ? route('groups.members', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members']);

        $requestsBaseUrl = Route::has('groups.requests')
            ? route('groups.requests', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members', 'request_tab' => 'pending']);

        $tabUrl = static fn (string $status): string => Route::has('groups.requests')
            ? route('groups.requests', ['group' => $groupRouteKey, 'status' => $status])
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members', 'request_tab' => 'pending', 'status' => $status]);
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Membership Queue</p>
                <h2 class="shell-title text-xl">{{ $group->name }}</h2>
            </div>
            <a href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back to Group</a>
        </div>
    </x-slot>

    @if (! $canManage)
        <section class="shell-card p-6 text-center">
            <h3 class="text-base font-semibold" style="color: var(--ui-text);">No access</h3>
            <p class="mt-2 text-sm shell-text-muted">Only group moderators and admins can review requests.</p>
        </section>
    @else
        <nav class="shell-card flex flex-wrap gap-2 p-3">
            <a href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Overview</a>
            <a href="{{ $membersUrl }}" class="btn-base btn-ghost px-3 py-2 text-sm">Members</a>
            <a href="{{ $requestsBaseUrl }}" class="btn-base btn-primary px-3 py-2 text-sm">Requests</a>
        </nav>

        <section class="shell-card p-5">
            <div class="mb-4 flex flex-wrap gap-2">
                <a href="{{ $tabUrl('pending') }}" class="rounded-full border px-3 py-1.5 text-xs font-semibold {{ $statusTab === 'pending' ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-[color:var(--ui-border)] text-[color:var(--ui-text)] hover:bg-[color:var(--ui-surface-muted)]' }}">Pending ({{ $pendingCount }})</a>
                <a href="{{ $tabUrl('approved') }}" class="rounded-full border px-3 py-1.5 text-xs font-semibold {{ $statusTab === 'approved' ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-[color:var(--ui-border)] text-[color:var(--ui-text)] hover:bg-[color:var(--ui-surface-muted)]' }}">Approved ({{ $approvedCount }})</a>
                <a href="{{ $tabUrl('rejected') }}" class="rounded-full border px-3 py-1.5 text-xs font-semibold {{ $statusTab === 'rejected' ? 'border-rose-300 bg-rose-50 text-rose-700' : 'border-[color:var(--ui-border)] text-[color:var(--ui-text)] hover:bg-[color:var(--ui-surface-muted)]' }}">Rejected ({{ $rejectedCount }})</a>
            </div>

            <div class="space-y-3">
                @forelse ($requestsPaginator as $requestMember)
                    @php
                        $statusValue = strtolower((string) ($requestMember->status ?? 'active'));
                        $statusClass = match ($statusValue) {
                            'pending' => 'bg-amber-50 text-amber-700',
                            'rejected', 'denied', 'banned' => 'bg-rose-50 text-rose-700',
                            default => 'bg-emerald-50 text-emerald-700',
                        };
                    @endphp

                    <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[color:var(--ui-border)] p-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <x-avatar :src="$requestMember->user?->avatar_url" :name="$requestMember->user?->name" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold" style="color: var(--ui-text);">{{ $requestMember->user?->name ?? 'Unknown user' }}</p>
                                <p class="truncate text-xs shell-text-muted">{{ $requestMember->user?->username ? '@'.$requestMember->user->username : 'User' }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">{{ \Illuminate\Support\Str::headline($statusValue) }}</span>

                            @if ($statusTab === 'pending')
                                <form method="POST" action="{{ route('groups.members.approve', ['group' => $groupRouteKey, 'membership' => $requestMember->id]) }}">
                                    @csrf
                                    <button type="submit" class="btn-base btn-primary px-3 py-1.5 text-xs">Approve</button>
                                </form>

                                <form method="POST" action="{{ route('groups.members.reject', ['group' => $groupRouteKey, 'membership' => $requestMember->id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-base btn-ghost px-3 py-1.5 text-xs">Reject</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <x-empty-state title="No Requests" description="No entries in this request state." />
                @endforelse
            </div>

            @if ($requestsPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $requestsPaginator->hasPages())
                <div class="mt-4">{{ $requestsPaginator->links() }}</div>
            @endif
        </section>
    @endif
</x-app-layout>
