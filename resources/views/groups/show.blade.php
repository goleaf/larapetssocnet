<x-app-layout>
    @php
        $groupRouteKey = $groupRouteKey ?? (filled((string) ($group->slug ?? '')) ? $group->slug : $group->id);
        $privacyValue = strtolower((string) ($group->privacy ?? (($group->is_private ?? false) ? 'private' : 'public')));
        $privacyLabel = \Illuminate\Support\Str::headline($privacyValue);
        $speciesLabel = \Illuminate\Support\Str::headline(str_replace(['-', '_'], ' ', (string) data_get($group, 'species', 'all pets')));

        $viewer = auth()->user();
        $membershipStatus = strtolower((string) data_get($membership, 'status', ''));
        $isPendingMembership = $membership && $membershipStatus === 'pending';

        $canPost = $isMember || $isAdmin || $isOwner;
        $canSeePosts = $privacyValue !== 'private' || $canPost;

        $membersUrl = Route::has('groups.members')
            ? route('groups.members', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members']);

        $requestsUrl = Route::has('groups.requests')
            ? route('groups.requests', $groupRouteKey)
            : route('groups.show', ['group' => $groupRouteKey, 'tab' => 'members', 'request_tab' => 'pending']);

        $coverUrl = (string) (data_get($group, 'cover_photo_url') ?: data_get($group, 'cover_image_path'));
        $avatarUrl = (string) (data_get($group, 'avatar_url') ?: data_get($group, 'profile_photo_url'));

        $sidebarMembers = collect();

        if ($activeMembers instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $sidebarMembers = $activeMembers->getCollection()->take(7);
        }

        if ($sidebarMembers->isEmpty() && class_exists(\App\Models\GroupMember::class)) {
            $sidebarMembers = \App\Models\GroupMember::query()
                ->where('group_id', $group->id)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhereIn('status', ['active', 'accepted']);
                })
                ->with('user:id,name,username')
                ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'moderator' THEN 3 ELSE 4 END")
                ->orderBy('joined_at')
                ->limit(7)
                ->get();
        }

        $pendingCount = $canManageMembers
            ? ($pendingMembers instanceof \Illuminate\Support\Collection
                ? $pendingMembers->count()
                : \App\Models\GroupMember::query()->where('group_id', $group->id)->where('status', 'pending')->count())
            : 0;

        $membersForPage = $activeMembers;

        if ($activeTab === 'members' && ! ($membersForPage instanceof \Illuminate\Pagination\LengthAwarePaginator)) {
            $membersForPage = \App\Models\GroupMember::query()
                ->where('group_id', $group->id)
                ->where(function ($query): void {
                    $query->whereNull('status')->orWhereIn('status', ['active', 'accepted']);
                })
                ->with('user:id,name,username')
                ->orderByRaw("CASE role WHEN 'owner' THEN 1 WHEN 'admin' THEN 2 WHEN 'moderator' THEN 3 ELSE 4 END")
                ->orderBy('joined_at')
                ->paginate(20)
                ->withQueryString();
        }
    @endphp

    <x-slot name="header">
        <x-ui.page-header :title="$group->name" :subtitle="$privacyLabel . ' · ' . $speciesLabel">
            <x-slot:action>
                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.button href="{{ route('groups.index') }}" variant="ghost" size="sm">Back</x-ui.button>

                    @auth
                        @if ($isOwner)
                            <x-ui.role-badge role="owner" />
                        @elseif ($isMember)
                            <form method="POST" action="{{ route('groups.leave', $groupRouteKey) }}">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="danger" size="sm">Leave</x-ui.button>
                            </form>
                        @elseif ($isPendingMembership)
                            <x-ui.badge variant="warning" size="md" :dot="true">Request Pending</x-ui.badge>
                        @elseif ($privacyValue !== 'secret')
                            <form method="POST" action="{{ route('groups.join', $groupRouteKey) }}">
                                @csrf
                                <x-ui.button type="submit" variant="primary" size="sm">
                                    {{ $privacyValue === 'public' ? 'Join Group' : 'Request to Join' }}
                                </x-ui.button>
                            </form>
                        @endif

                        @if ($isAdmin || $isOwner)
                            <x-ui.button href="{{ route('groups.edit', $groupRouteKey) }}" variant="ghost" size="sm">Edit</x-ui.button>
                        @endif
                    @endauth
                </div>
            </x-slot:action>
        </x-ui.page-header>
    </x-slot>

    <x-ui.card padding="none" class="mb-6 overflow-hidden">
        <div class="h-36 sm:h-44 w-full">
            @if ($coverUrl !== '')
                <img src="{{ $coverUrl }}" alt="{{ $group->name }} cover" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full" style="background: linear-gradient(120deg, color-mix(in srgb, var(--color-paw) 24%, var(--color-warm-white) 76%), color-mix(in srgb, var(--color-sky) 22%, var(--color-warm-white) 78%));"></div>
            @endif
        </div>

        <div class="relative px-5 pb-5 pt-0 sm:px-6">
            <div class="-mt-10 flex flex-wrap items-end gap-4">
                <x-avatar :src="$avatarUrl !== '' ? $avatarUrl : null" :name="$group->name" size="2xl" class="ring-4 ring-warm-white bg-warm-white bg-opacity-100" />
                
                <div class="min-w-0 flex-1 pb-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.badge variant="default">{{ $membersCount }} members</x-ui.badge>
                        <x-ui.badge variant="default">{{ $postsCount }} posts</x-ui.badge>
                        <x-ui.badge variant="default">{{ $eventsCount }} events</x-ui.badge>
                    </div>
                    @if (filled((string) $group->description))
                        <p class="mt-2 text-sm text-fur">{{ $group->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </x-ui.card>

    @php
        $navTabs = [
            ['label' => 'Overview', 'value' => 'feed'],
            ['label' => 'Members', 'value' => 'members'],
        ];
        if ($canManageMembers) {
            $navTabs[] = ['label' => 'Requests', 'value' => 'pending', 'count' => $pendingCount];
        }
        $navTabs[] = ['label' => 'About', 'value' => 'about'];
        
        $currentActivityTab = $activeTab === 'members' && request()->string('request_tab')->toString() === 'pending' ? 'pending' : $activeTab;
    @endphp

    <div class="mb-6">
        <x-ui.tabs :tabs="$navTabs" :active="$currentActivityTab" paramName="tab" />
    </div>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_19rem]">
        <section class="space-y-4">
            @if (session('status'))
                <x-flash-message type="success" :message="session(' status')" />
            @endif

            @if ($errors->any())
                <x-flash-message type="error" :message="$errors->first()" />
            @endif

            @if ($activeTab === 'about')
                <x-ui.card padding="lg" class="space-y-6">
                    <div>
                        <x-ui.section title="About this group" tight />
                        <p class="text-sm text-fur">{{ $group->description ?: 'No description yet.' }}</p>
                    </div>

                    <x-ui.divider />

                    <div>
                        <x-ui.section title="Rules" tight />
                        <p class="mt-2 whitespace-pre-line text-sm text-fur">{{ $group->rules ?: 'No published rules yet.' }}</p>
                    </div>
                </x-ui.card>
            @elseif ($activeTab === 'events')
                <x-ui.card padding="lg">
                    <x-ui.card-header title="Group Events" />

                    <div class="mt-4 space-y-3">
                        @forelse ($events ?? [] as $eventItem)
                            @php
                                $startAtRaw = $eventItem->start_at ?? $eventItem->starts_at;
                                $location = $eventItem->location_text ?? $eventItem->location;
                                $eventStatus = $eventItem->status ?? 'scheduled';
                            @endphp

                            <x-ui.card padding="sm" :hover="true" class="flex flex-col gap-1">
                                <h4 class="font-semibold">
                                    <a href="{{ route('events.show', $eventItem->id) }}" class="hover:underline text-bark">{{ $eventItem->title }}</a>
                                </h4>
                                <p class="text-xs text-fur">
                                    {{ $startAtRaw ? \Carbon\Carbon::parse($startAtRaw)->format('M j, Y g:i A') : 'TBA' }}
                                    @if ($location)
                                        · {{ $location }}
                                    @endif
                                </p>
                                <div>
                                    <x-ui.badge variant="{{ strtolower($eventStatus) === 'scheduled' ? 'warning' : 'default' }}" size="sm">{{ \Illuminate\Support\Str::headline($eventStatus) }}</x-ui.badge>
                                </div>
                            </x-ui.card>
                        @empty
                            <x-ui.empty-state title="No events yet" description="This group hasn' t scheduled any events." icon="📅" />
                        @endforelse
                    </div>

                    @if ($events instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4">{{ $events->links() }}</div>
                    @endif
                </x-ui.card>
            @elseif ($activeTab === 'members')
                @if (request()->string('request_tab')->toString() === 'pending' && $canManageMembers)
                    <x-ui.card padding="lg">
                        <x-ui.card-header title="Pending Requests" />
                        <div class="mt-4 space-y-3">
                            @forelse ($pendingMembers as $pending)
                                <x-ui.user-row :user="$pending->user" subtitle="Pending member" class="border border-whisker/30 rounded-xl px-3 bg-warm-white">
                                    <x-slot:action>
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('groups.requests.approve', ['group' => $groupRouteKey, 'membership' => $pending->id]) }}">
                                                @csrf
                                                <x-ui.button type="submit" variant="success" size="sm">Approve</x-ui.button>
                                            </form>
                                            <form method="POST" action="{{ route('groups.members.reject', ['group' => $groupRouteKey, 'membership' => $pending->id]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-ui.button type="submit" variant="ghost" size="sm">Reject</x-ui.button>
                                            </form>
                                        </div>
                                    </x-slot:action>
                                </x-ui.user-row>
                            @empty
                                <x-ui.empty-state title="No pending requests" description="No one is waiting to join the group." icon="📩" />
                            @endforelse
                        </div>
                    </x-ui.card>
                @else
                    <x-ui.card padding="lg">
                        <x-ui.card-header title="Members" />
                        <div class="mt-4 space-y-3">
                            @forelse ($membersForPage ?? [] as $memberItem)
                                @php
                                    $roleValue = strtolower((string) ($memberItem->role ?? 'member'));
                                @endphp
                                <x-ui.user-row :user="$memberItem->user" :role="$roleValue" class="border border-whisker/30 rounded-xl px-3 bg-warm-white">
                                    <x-slot:action>
                                        @if ($canManageMembers && $roleValue !== 'owner')
                                            <div x-data="dropdownState()" class="relative">
                                                <x-ui.button variant="ghost" size="sm" @click="toggle()">Manage ▾</x-ui.button>
                                                
                                                <div x-show="open" x-cloak @click.outside="close()" class="absolute right-0 mt-1 min-w-[200px] bg-warm-white border border-whisker/30 shadow-card-hover rounded-lg z-30 p-2 space-y-2">
                                                    <form method="POST" action="{{ route('groups.members.role', ['group' => $groupRouteKey, 'membership' => $memberItem->id]) }}" class="flex items-center gap-2">
                                                        @csrf
                                                        @method('PATCH')
                                                        <select name="role" class="block w-full rounded-md border-whisker/50 py-1.5 pl-3 pr-8 text-sm text-bark focus:border-paw focus:ring-paw bg-cream">
                                                            <option value="member" @selected($roleValue === 'member')>Member</option>
                                                            <option value="moderator" @selected($roleValue === 'moderator')>Moderator</option>
                                                            <option value="admin" @selected($roleValue === 'admin')>Admin</option>
                                                        </select>
                                                        <x-ui.icon-button type="submit" variant="ghost" size="sm" title="Save">✓</x-ui.icon-button>
                                                    </form>
                                                    <x-ui.divider class="!my-2" />
                                                    <form method="POST" action="{{ route('groups.members.ban', ['group' => $groupRouteKey, 'membership' => $memberItem->id]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <x-ui.button type="submit" variant="danger" :full="true" size="sm">Ban Member</x-ui.button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endif
                                    </x-slot:action>
                                </x-ui.user-row>
                            @empty
                                <x-ui.empty-state title="No members yet" description="This group currently has no members." icon="👥" />
                            @endforelse
                        </div>

                        @if ($membersForPage instanceof \Illuminate\Pagination\LengthAwarePaginator)
                            <div class="mt-4">{{ $membersForPage->links() }}</div>
                        @endif
                    </x-ui.card>
                @endif
            @else
                @if ($canPost)
                    <x-ui.card padding="lg">
                        <x-ui.card-header title="Share in this group" />
                        <form method="POST" action="{{ route('groups.posts.attach', $groupRouteKey) }}" class="space-y-4">
                            @csrf
                            <x-ui.input name="post_id" type="number" label="Attach Existing Post ID (optional)" :value="old(' post_id')" min="1" />
                            <x-ui.textarea name="body" label="Or create new post" rows="3" placeholder="Write something for this group...">{{ old('body') }}</x-ui.textarea>
                            <div class="flex justify-end">
                                <x-ui.button type="submit" variant="primary">Publish</x-ui.button>
                            </div>
                        </form>
                    </x-ui.card>
                @endif

                @if (! $canSeePosts)
                    <article class="shell-card p-6 text-center">
                        <h3 class="text-base font-semibold" style="color: var(--ui-text);">Private group content</h3>
                        <p class="mt-2 text-sm shell-text-muted">Join this group to view and participate in posts.</p>
                    </article>
                @else
                    <div class="space-y-4">
                        @forelse ($feedPosts ?? [] as $post)
                            @include('partials.post-card', ['post' => $post, 'viewer' => $viewer])
                        @empty
                            <x-empty-state
                                title="No Group Posts Yet"
                                description="Start the conversation by sharing the first post."
                            />
                        @endforelse

                        @if ($feedPosts instanceof \Illuminate\Pagination\LengthAwarePaginator)
                            <div class="shell-card p-4">{{ $feedPosts->links() }}</div>
                        @endif
                    </div>
                @endif
            @endif
        </section>

        <aside class="space-y-4">
            <x-ui.card padding="md">
                <x-ui.card-header title="Group Snapshot" />
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg border border-whisker/30 p-2 bg-warm-white">
                        <p class="text-sm font-semibold text-bark">{{ $membersCount }}</p>
                        <p class="text-[11px] text-fur uppercase tracking-wide">Members</p>
                    </div>
                    <div class="rounded-lg border border-whisker/30 p-2 bg-warm-white">
                        <p class="text-sm font-semibold text-bark">{{ $postsCount }}</p>
                        <p class="text-[11px] text-fur uppercase tracking-wide">Posts</p>
                    </div>
                    <div class="rounded-lg border border-whisker/30 p-2 bg-warm-white">
                        <p class="text-sm font-semibold text-bark">{{ $eventsCount }}</p>
                        <p class="text-[11px] text-fur uppercase tracking-wide">Events</p>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card padding="md">
                <x-slot:header>
                    <x-ui.card-header title="Members" class="pb-3 mb-3">
                        <x-slot:action>
                            <a href="{{ $membersUrl }}" class="text-xs font-semibold hover:underline text-paw transition-colors">View all</a>
                        </x-slot:action>
                    </x-ui.card-header>
                </x-slot:header>

                <div class="space-y-1">
                    @forelse ($sidebarMembers as $memberItem)
                        <x-ui.user-row :user="$memberItem->user" :role="strtolower((string) ($memberItem->role ?? ' member'))" class="border border-whisker/30 rounded-xl px-2.5 bg-warm-white !py-1" />
                    @empty
                        <p class="text-sm text-fur">No members yet.</p>
                    @endforelse
                </div>
            </x-ui.card>

            @if ($canManageMembers)
                <x-ui.card padding="md">
                    <x-slot:header>
                        <x-ui.card-header title="Join Requests" class="pb-3 mb-3">
                            <x-slot:action>
                                <x-ui.badge variant="warning" size="sm">{{ $pendingCount }}</x-ui.badge>
                            </x-slot:action>
                        </x-ui.card-header>
                    </x-slot:header>

                    @if ($pendingCount > 0)
                        <p class="text-sm text-fur mb-4">You have pending member requests to review.</p>
                        <x-ui.button href="{{ $requestsUrl }}" variant="primary" :full="true" size="sm">Review Requests</x-ui.button>
                    @else
                        <p class="text-sm text-fur">No pending requests right now.</p>
                    @endif
                </x-ui.card>
            @endif
        </aside>
    </div>
</x-app-layout>
