<x-app-layout>
    @php
        $tabs = [
            'feed' => 'Feed',
            'members' => 'Members',
            'events' => 'Events',
            'about' => 'About',
        ];
        $rulesText = $group->rules ?? null;
        $groupRouteKey = $groupRouteKey ?? (filled((string) ($group->slug ?? '')) ? $group->slug : $group->id);
        $privacyValue = strtolower((string) ($group->privacy ?? (($group->is_private ?? false) ? 'private' : 'public')));
    @endphp

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $group->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $privacyLabel }} group
                    @if ($owner)
                        · by {{ $owner->name }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('groups.index') }}" class="btn-base btn-ghost px-3 py-2 text-sm">Back</a>

                @auth
                    @if ($isMember)
                        @if (! $isOwner)
                            <form method="POST" action="{{ route('groups.leave', $groupRouteKey) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-base btn-ghost px-3 py-2 text-sm">Leave</button>
                            </form>
                        @else
                            <span class="chip">Owner</span>
                        @endif
                    @elseif ($membership && $membership->status === 'pending')
                        <span class="chip">Request Pending</span>
                    @else
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

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <x-flash-message type="success" :message="session('status')" />
            @endif

            @if ($errors->any())
                <x-flash-message type="error" :message="$errors->first()" />
            @endif

            <section class="shell-card p-5">
                <div class="flex flex-wrap items-center gap-3 text-sm shell-text-muted">
                    <span><strong>{{ $membersCount }}</strong> members</span>
                    <span><strong>{{ $postsCount }}</strong> posts</span>
                    <span><strong>{{ $eventsCount }}</strong> events</span>
                    <span class="chip">{{ \Illuminate\Support\Str::headline($privacyValue) }}</span>
                </div>
                @if (! empty($group->description))
                    <p class="mt-3 text-sm">{{ $group->description }}</p>
                @endif
            </section>

            <nav class="shell-card flex flex-wrap gap-2 p-3">
                @foreach ($tabs as $key => $label)
                    <a
                        href="{{ route('groups.show', ['group' => $groupRouteKey, 'tab' => $key]) }}"
                        class="btn-base px-3 py-2 text-sm {{ $activeTab === $key ? 'btn-primary' : 'btn-ghost' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </nav>

            @if ($activeTab === 'feed')
                @if ($isMember)
                    <section class="shell-card space-y-4 p-5">
                        <h3 class="shell-title text-base">Share in Group</h3>
                        <form method="POST" action="{{ route('groups.posts.attach', $groupRouteKey) }}" class="space-y-3">
                            @csrf
                            <div>
                                <x-input-label for="post_id" :value="'Attach Existing Post ID (optional)'" />
                                <x-text-input id="post_id" name="post_id" type="number" class="mt-1 block w-full" :value="old('post_id')" min="1" />
                            </div>
                            <div>
                                <x-input-label for="body" :value="'Or create a new group post'" />
                                <textarea
                                    id="body"
                                    name="body"
                                    rows="3"
                                    class="mt-1 block w-full rounded-md border border-[var(--ui-border)] bg-white px-3 py-2 text-sm focus:border-[var(--ui-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--ui-primary)]/25"
                                    placeholder="Write something for this group..."
                                >{{ old('body') }}</textarea>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="btn-base btn-primary">Attach Post</button>
                            </div>
                        </form>
                    </section>
                @endif

                <section class="space-y-4">
                    @forelse ($feedPosts ?? [] as $post)
                        @include('posts.partials.card', ['post' => $post])
                    @empty
                        <x-empty-state
                            title="No Group Posts Yet"
                            description="Start the conversation by sharing the first post."
                        />
                    @endforelse

                    @if ($feedPosts)
                        {{ $feedPosts->links() }}
                    @endif
                </section>
            @endif

            @if ($activeTab === 'members')
                <section class="shell-card p-5">
                    <h3 class="shell-title text-base">Members</h3>
                    <div class="mt-4 space-y-3">
                        @forelse ($activeMembers ?? [] as $member)
                            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
                                <div>
                                    <p class="font-medium">{{ $member->user?->name ?? 'Unknown user' }}</p>
                                    <p class="text-xs shell-text-muted">
                                        @if ($member->user?->username)
                                            &#64;{{ $member->user->username }}
                                        @endif
                                        · {{ \Illuminate\Support\Str::headline($member->role ?? 'member') }}
                                    </p>
                                </div>

                                @if ($canManageMembers && ($member->role !== 'owner'))
                                    <form method="POST" action="{{ route('groups.members.role', ['group' => $groupRouteKey, 'membership' => $member->id]) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="rounded-md border border-[var(--ui-border)] px-2 py-1 text-sm">
                                            <option value="member" @selected($member->role === 'member')>Member</option>
                                            <option value="admin" @selected($member->role === 'admin')>Admin</option>
                                        </select>
                                        <button type="submit" class="btn-base btn-ghost px-3 py-2 text-xs">Update</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm shell-text-muted">No members yet.</p>
                        @endforelse
                    </div>

                    @if ($activeMembers)
                        <div class="mt-4">
                            {{ $activeMembers->links() }}
                        </div>
                    @endif
                </section>

                @if ($canManageMembers)
                    <section class="shell-card p-5">
                        <h3 class="shell-title text-base">Pending Requests</h3>
                        <div class="mt-4 space-y-3">
                            @forelse ($pendingMembers as $pending)
                                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--ui-border)] px-4 py-3">
                                    <div>
                                        <p class="font-medium">{{ $pending->user?->name ?? 'Unknown user' }}</p>
                                        <p class="text-xs shell-text-muted">
                                            @if ($pending->user?->username)
                                                &#64;{{ $pending->user->username }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <form method="POST" action="{{ route('groups.members.approve', ['group' => $groupRouteKey, 'membership' => $pending->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn-base btn-primary px-3 py-2 text-xs">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('groups.members.reject', ['group' => $groupRouteKey, 'membership' => $pending->id]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-base btn-ghost px-3 py-2 text-xs">Reject</button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <p class="text-sm shell-text-muted">No pending requests.</p>
                            @endforelse
                        </div>
                    </section>
                @endif
            @endif

            @if ($activeTab === 'events')
                <section class="shell-card p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="shell-title text-base">Group Events</h3>
                        @auth
                            @if ($isMember || $isAdmin || $isOwner)
                                <a href="{{ route('events.create', ['group_id' => $group->id]) }}" class="btn-base btn-primary px-3 py-2 text-sm">Create Event</a>
                            @endif
                        @endauth
                    </div>

                    <div class="mt-4 grid gap-3">
                        @forelse ($events ?? [] as $eventItem)
                            @php
                                $startAtRaw = $eventItem->start_at ?? $eventItem->starts_at;
                                $location = $eventItem->location_text ?? $eventItem->location;
                                $eventStatus = $eventItem->status ?? 'scheduled';
                            @endphp
                            <article class="rounded-xl border border-[var(--ui-border)] p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h4 class="font-semibold">
                                            <a href="{{ route('events.show', $eventItem->id) }}">{{ $eventItem->title }}</a>
                                        </h4>
                                        <p class="mt-1 text-xs shell-text-muted">
                                            {{ $startAtRaw ? \Carbon\Carbon::parse($startAtRaw)->format('M j, Y g:i A') : 'TBA' }}
                                            @if ($location)
                                                · {{ $location }}
                                            @endif
                                        </p>
                                    </div>
                                    <span class="chip">{{ \Illuminate\Support\Str::headline($eventStatus) }}</span>
                                </div>
                            </article>
                        @empty
                            <p class="text-sm shell-text-muted">No events yet.</p>
                        @endforelse
                    </div>

                    @if ($events)
                        <div class="mt-4">
                            {{ $events->links() }}
                        </div>
                    @endif
                </section>
            @endif

            @if ($activeTab === 'about')
                <section class="shell-card space-y-4 p-5">
                    <div>
                        <h3 class="shell-title text-base">Description</h3>
                        <p class="mt-2 text-sm">
                            {{ $group->description ?: 'No description yet.' }}
                        </p>
                    </div>

                    <div>
                        <h3 class="shell-title text-base">Rules</h3>
                        <p class="mt-2 whitespace-pre-line text-sm">
                            {{ $rulesText ?: 'No rules published yet.' }}
                        </p>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
