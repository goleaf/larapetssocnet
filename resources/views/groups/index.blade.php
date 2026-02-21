<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Group Discovery</h2>
            @auth
                <a href="{{ route('groups.create') }}" class="btn-base btn-primary px-3 py-2 text-sm">Create Group</a>
            @endauth
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <x-flash-message type="success" :message="session('status')" />
            @endif

            <form method="GET" action="{{ route('groups.index') }}" class="shell-card grid gap-3 p-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <x-input-label for="q" :value="'Search'" />
                    <x-text-input id="q" name="q" type="text" class="mt-1 block w-full" :value="$search" placeholder="Search groups..." />
                </div>
                <div>
                    <x-input-label for="privacy" :value="'Type'" />
                    <select id="privacy" name="privacy" class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm">
                        <option value="all" @selected($privacy === 'all')>All</option>
                        <option value="public" @selected($privacy === 'public')>Public</option>
                        <option value="private" @selected($privacy === 'private')>Private</option>
                        <option value="secret" @selected($privacy === 'secret')>Secret</option>
                        @auth
                            <option value="joined" @selected($privacy === 'joined')>Joined</option>
                            <option value="owned" @selected($privacy === 'owned')>Owned</option>
                        @endauth
                    </select>
                </div>
                <div>
                    <x-input-label for="sort" :value="'Sort'" />
                    <select id="sort" name="sort" class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm">
                        <option value="latest" @selected($sort === 'latest')>Latest</option>
                        <option value="members" @selected($sort === 'members')>Most Members</option>
                        <option value="name" @selected($sort === 'name')>Name</option>
                    </select>
                </div>
                <div class="md:col-span-4 flex justify-end">
                    <button type="submit" class="btn-base btn-primary">Apply Filters</button>
                </div>
            </form>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($groups as $group)
                    @php
                        $groupRouteKey = filled((string) ($group->slug ?? '')) ? $group->slug : $group->id;
                        $ownerId = $group->owner_user_id ?? $group->owner_id;
                        $owner = $owners->get($ownerId);
                        $membership = $membershipByGroup->get($group->id);
                        $isMember = $membership && (is_null($membership->status) || $membership->status === 'active');
                        $isPending = $membership && $membership->status === 'pending';
                        $privacyValue = strtolower((string) ($group->privacy ?? (($group->is_private ?? false) ? 'private' : 'public')));
                    @endphp

                    <article class="shell-card p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="shell-title text-lg">
                                    <a href="{{ route('groups.show', $groupRouteKey) }}">{{ $group->name }}</a>
                                </h3>
                                @if ($owner)
                                    <p class="mt-1 text-xs shell-text-muted">By {{ $owner->name }}</p>
                                @endif
                            </div>
                            <span class="chip">{{ \Illuminate\Support\Str::headline($privacyValue) }}</span>
                        </div>

                        @if (! empty($group->description))
                            <p class="mt-3 text-sm shell-text-muted">{{ \Illuminate\Support\Str::limit($group->description, 180) }}</p>
                        @endif

                        <div class="mt-4 flex items-center justify-between text-xs shell-text-muted">
                            <span>{{ $group->members_count ?? 0 }} members</span>
                            <span>{{ $group->posts_count ?? 0 }} posts</span>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <a href="{{ route('groups.show', $groupRouteKey) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Open</a>

                            @auth
                                @if ($isMember)
                                    <span class="chip">Member</span>
                                @elseif ($isPending)
                                    <span class="chip">Request Pending</span>
                                @else
                                    <form method="POST" action="{{ route('groups.join', $groupRouteKey) }}">
                                        @csrf
                                        <button type="submit" class="btn-base btn-primary px-3 py-2 text-sm">
                                            {{ $privacyValue === 'public' ? 'Join' : 'Request' }}
                                        </button>
                                    </form>
                                @endif
                            @else
                                <a href="{{ route('login') }}" class="btn-base btn-primary px-3 py-2 text-sm">Sign in to Join</a>
                            @endauth
                        </div>
                    </article>
                @empty
                    <x-empty-state
                        title="No Groups Found"
                        description="Try changing your search or filters."
                    />
                @endforelse
            </div>

            <div>
                {{ $groups->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
