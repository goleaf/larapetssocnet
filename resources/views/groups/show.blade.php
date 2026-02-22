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
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="shell-kicker">Group Space</p>
                <h2 class="shell-title text-xl leading-tight">{{ $group->name }}</h2>
                <p class="mt-1 text-sm shell-text-muted">{{ $privacyLabel }} · {{ $speciesLabel }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('groups.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back</a>

                @auth
                    @if ($isOwner)
                        <span class="chip">Owner</span>
                    @elseif ($isMember)
                        <form method="POST" action="{{ route('groups.leave', $groupRouteKey) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-base btn-ghost px-3 py-2 text-sm">Leave</button>
                        </form>
                    @elseif ($isPendingMembership)
                        <span class="chip">Request Pending</span>
                    @elseif ($privacyValue !== 'secret')
                        <form method="POST" action="{{ route('groups.join', $groupRouteKey) }}">
                            @csrf
                            <button type="submit" class="btn-base btn-primary px-3 py-2 text-sm">
                                {{ $privacyValue === 'public' ? 'Join Group' : 'Request to Join' }}
                            </button>
                        </form>
                    @endif

                    @if ($isAdmin || $isOwner)
                        <a href="{{ route('groups.edit', $groupRouteKey) }}" class="btn-base btn-ghost px-3 py-2 text-sm">Edit</a>
                    @endif
                @endauth
            </div>
        </div>
    </x-slot>

    <section class="overflow-hidden rounded-2xl border border-[color:var(--ui-border)] bg-[color:var(--ui-surface)]">
        <div class="h-36 w-full sm:h-44">
            @if ($coverUrl !== '')
                <img src="{{ $coverUrl }}" alt="{{ $group->name }} cover" class="h-full w-full object-cover">
            @else
                <div class="h-full w-full" style="background: linear-gradient(120deg, color-mix(in srgb, var(--ui-primary) 24%, var(--ui-surface) 76%), color-mix(in srgb, var(--ui-accent) 22%, var(--ui-surface) 78%));"></div>
            @endif
        </div>

        <div class="relative px-5 pb-5 pt-0 sm:px-6">
            <div class="-mt-10 flex flex-wrap items-end gap-4">
                <div class="h-20 w-20 overflow-hidden rounded-full border-4 border-[color:var(--ui-surface)] bg-[color:var(--ui-surface-muted)] sm:h-24 sm:w-24">
                    @if ($avatarUrl !== '')
                        <img src="{{ $avatarUrl }}" alt="{{ $group->name }} avatar" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full w-full items-center justify-center text-3xl">🐾</div>
                    @endif
                </div>

                <div class="min-w-0 flex-1 pb-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="chip">{{ $membersCount }} members</span>
                        <span class="chip">{{ $postsCount }} posts</span>
                        <span class="chip">{{ $eventsCount }} events</span>
                    </div>
                    @if (filled((string) $group->description))
                        <p class="mt-2 text-sm shell-text-muted">{{ $group->description }}</p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <nav class="shell-card flex flex-wrap gap-2 p-3">
        <a href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'feed']) }}" class="btn-base px-3 py-2 text-sm {{ $activeTab === 'feed' ? 'btn-primary' : 'btn-ghost' }}">Overview</a>
        <a href="{{ $membersUrl }}" class="btn-base px-3 py-2 text-sm {{ $activeTab === 'members' && request()->string('request_tab')->toString() !== 'pending' ? 'btn-primary' : 'btn-ghost' }}">Members</a>
        @if ($canManageMembers)
            <a href="{{ $requestsUrl }}" class="btn-base px-3 py-2 text-sm {{ request()->string('request_tab')->toString() === 'pending' ? 'btn-primary' : 'btn-ghost' }}">Requests ({{ $pendingCount }})</a>
        @endif
        <a href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => 'about']) }}" class="btn-base px-3 py-2 text-sm {{ $activeTab === 'about' ? 'btn-primary' : 'btn-ghost' }}">About</a>
    </nav>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_19rem]">
        <section class="space-y-4">
            @if (session('status'))
                <x-flash-message type="success" :message="session('status')" />
            @endif

            @if ($errors->any())
                <x-flash-message type="error" :message="$errors->first()" />
            @endif

            @if ($activeTab === 'about')
                <article class="shell-card space-y-5 p-5">
                    <div>
                        <h3 class="text-base font-semibold" style="color: var(--ui-text);">About this group</h3>
                        <p class="mt-2 text-sm shell-text-muted">{{ $group->description ?: 'No description yet.' }}</p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold" style="color: var(--ui-text);">Rules</h3>
                        <p class="mt-2 whitespace-pre-line text-sm shell-text-muted">{{ $group->rules ?: 'No published rules yet.' }}</p>
                    </div>
                </article>
            @elseif ($activeTab === 'events')
                <article class="shell-card p-5">
                    <h3 class="text-base font-semibold" style="color: var(--ui-text);">Group Events</h3>

                    <div class="mt-4 space-y-3">
                        @forelse ($events ?? [] as $eventItem)
                            @php
                                $startAtRaw = $eventItem->start_at ?? $eventItem->starts_at;
                                $location = $eventItem->location_text ?? $eventItem->location;
                                $eventStatus = $eventItem->status ?? 'scheduled';
                            @endphp

                            <article class="rounded-xl border border-[color:var(--ui-border)] p-4">
                                <h4 class="font-semibold">
                                    <a href="{{ route('events.show', $eventItem->id) }}" class="hover:underline">{{ $eventItem->title }}</a>
                                </h4>
                                <p class="mt-1 text-xs shell-text-muted">
                                    {{ $startAtRaw ? \Carbon\Carbon::parse($startAtRaw)->format('M j, Y g:i A') : 'TBA' }}
                                    @if ($location)
                                        · {{ $location }}
                                    @endif
                                </p>
                                <span class="mt-2 inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ \Illuminate\Support\Str::headline($eventStatus) }}</span>
                            </article>
                        @empty
                            <p class="text-sm shell-text-muted">No events yet.</p>
                        @endforelse
                    </div>

                    @if ($events instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-4">{{ $events->links() }}</div>
                    @endif
                </article>
            @elseif ($activeTab === 'members')
                @if (request()->string('request_tab')->toString() === 'pending' && $canManageMembers)
                    <article class="shell-card p-5">
                        <h3 class="text-base font-semibold" style="color: var(--ui-text);">Pending Requests</h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($pendingMembers as $pending)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[color:var(--ui-border)] p-3">
                                    <div>
                                        <p class="font-medium">{{ $pending->user?->name ?? 'Unknown user' }}</p>
                                        <p class="text-xs shell-text-muted">{{ $pending->user?->username ? '@'.$pending->user->username : 'Pending member' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('groups.requests.approve', ['group' => $groupRouteKey, 'membership' => $pending->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn-base btn-primary px-3 py-1.5 text-xs">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('groups.members.reject', ['group' => $groupRouteKey, 'membership' => $pending->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-base btn-ghost px-3 py-1.5 text-xs">Reject</button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm shell-text-muted">No pending requests.</p>
                            @endforelse
                        </div>
                    </article>
                @else
                    <article class="shell-card p-5">
                        <h3 class="text-base font-semibold" style="color: var(--ui-text);">Members</h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($membersForPage ?? [] as $memberItem)
                                @php
                                    $roleValue = strtolower((string) ($memberItem->role ?? 'member'));
                                    $roleClass = match ($roleValue) {
                                        'owner' => 'bg-purple-50 text-purple-700',
                                        'admin' => 'bg-indigo-50 text-indigo-700',
                                        'moderator' => 'bg-amber-50 text-amber-700',
                                        default => 'bg-slate-100 text-slate-700',
                                    };
                                @endphp
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[color:var(--ui-border)] p-3">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{{ $memberItem->user?->name ?? 'Unknown user' }}</p>
                                        <p class="truncate text-xs shell-text-muted">{{ $memberItem->user?->username ? '@'.$memberItem->user->username : 'Member' }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $roleClass }}">{{ \Illuminate\Support\Str::headline($roleValue) }}</span>

                                        @if ($canManageMembers && $roleValue !== 'owner')
                                            <form method="POST" action="{{ route('groups.members.role', ['group' => $groupRouteKey, 'membership' => $memberItem->id]) }}" class="flex items-center gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <select name="role" class="rounded-md border border-[color:var(--ui-border)] px-2 py-1 text-xs">
                                                    <option value="member" @selected($roleValue === 'member')>Member</option>
                                                    <option value="moderator" @selected($roleValue === 'moderator')>Moderator</option>
                                                    <option value="admin" @selected($roleValue === 'admin')>Admin</option>
                                                </select>
                                                <button type="submit" class="btn-base btn-ghost px-2.5 py-1.5 text-xs">Save</button>
                                            </form>

                                            <form method="POST" action="{{ route('groups.members.ban', ['group' => $groupRouteKey, 'membership' => $memberItem->id]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn-base btn-ghost px-2.5 py-1.5 text-xs text-red-600">Ban</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm shell-text-muted">No members yet.</p>
                            @endforelse
                        </div>

                        @if ($membersForPage instanceof \Illuminate\Pagination\LengthAwarePaginator)
                            <div class="mt-4">{{ $membersForPage->links() }}</div>
                        @endif
                    </article>
                @endif
            @else
                @if ($canPost)
                    <article class="shell-card space-y-3 p-5">
                        <h3 class="text-base font-semibold" style="color: var(--ui-text);">Share in this group</h3>
                        <form method="POST" action="{{ route('groups.posts.attach', $groupRouteKey) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label for="post_id" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Attach Existing Post ID (optional)</label>
                                <x-text-input id="post_id" name="post_id" type="number" class="block w-full" :value="old('post_id')" min="1" />
                            </div>
                            <div>
                                <label for="body" class="mb-1 block text-xs font-semibold uppercase tracking-[0.08em] shell-text-muted">Or create new post</label>
                                <textarea id="body" name="body" rows="3" class="form-textarea" placeholder="Write something for this group...">{{ old('body') }}</textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="btn-base btn-primary">Publish</button>
                            </div>
                        </form>
                    </article>
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
            <section class="shell-card p-4">
                <p class="shell-kicker">Group Snapshot</p>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg border border-[color:var(--ui-border)] p-2">
                        <p class="text-sm font-semibold" style="color: var(--ui-text);">{{ $membersCount }}</p>
                        <p class="text-[11px] shell-text-muted">Members</p>
                    </div>
                    <div class="rounded-lg border border-[color:var(--ui-border)] p-2">
                        <p class="text-sm font-semibold" style="color: var(--ui-text);">{{ $postsCount }}</p>
                        <p class="text-[11px] shell-text-muted">Posts</p>
                    </div>
                    <div class="rounded-lg border border-[color:var(--ui-border)] p-2">
                        <p class="text-sm font-semibold" style="color: var(--ui-text);">{{ $eventsCount }}</p>
                        <p class="text-[11px] shell-text-muted">Events</p>
                    </div>
                </div>
            </section>

            <section class="shell-card p-4">
                <div class="mb-2 flex items-center justify-between">
                    <p class="shell-kicker">Members</p>
                    <a href="{{ $membersUrl }}" class="text-xs font-semibold hover:underline" style="color: var(--ui-primary);">View all</a>
                </div>

                <div class="space-y-2">
                    @forelse ($sidebarMembers as $memberItem)
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-[color:var(--ui-border)] px-2.5 py-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold" style="color: var(--ui-text);">{{ $memberItem->user?->name ?? 'Unknown user' }}</p>
                                <p class="truncate text-xs shell-text-muted">{{ $memberItem->user?->username ? '@'.$memberItem->user->username : 'Member' }}</p>
                            </div>
                            <span class="text-[11px] shell-text-muted">{{ \Illuminate\Support\Str::headline((string) ($memberItem->role ?? 'member')) }}</span>
                        </div>
                    @empty
                        <p class="text-sm shell-text-muted">No members yet.</p>
                    @endforelse
                </div>
            </section>

            @if ($canManageMembers)
                <section class="shell-card p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="shell-kicker">Join Requests</p>
                        <span class="chip">{{ $pendingCount }}</span>
                    </div>

                    @if ($pendingCount > 0)
                        <p class="text-sm shell-text-muted">You have pending member requests to review.</p>
                        <a href="{{ $requestsUrl }}" class="btn-base btn-primary mt-3 w-full px-3 py-2 text-sm">Review Requests</a>
                    @else
                        <p class="text-sm shell-text-muted">No pending requests right now.</p>
                    @endif
                </section>
            @endif
        </aside>
    </div>
</x-app-layout>
