<x-app-layout>
 @php
 $groupRouteKey = $groupRouteKey ?? (filled((string) ($group->slug ??'')) ? $group->slug : $group->id);
 $privacyValue = $privacyValue ?? strtolower((string) $group->normalizedPrivacy());
 $privacyLabel = $privacyLabel ?? \Illuminate\Support\Str::headline($privacyValue);
 $speciesLabel = $speciesLabel ?? \Illuminate\Support\Str::headline(str_replace(['-','_'],'', (string) data_get($group,'species','all pets')));
 $isArchived = $isArchived ?? (method_exists($group, 'isArchived') && $group->isArchived());
 $canPost = $canPost ?? ($isMember || $isAdmin || $isOwner);
 $canSeePosts = $canSeePosts ?? ($canViewPosts ?? ($privacyValue !=='private' || $canPost));
 $membersUrl = $membersUrl ?? route('groups.members.index', $groupRouteKey);
 $requestsUrl = $requestsUrl ?? route('groups.requests.index', $groupRouteKey);

 $coverUrl = (string) (data_get($group,'cover_photo_url') ?: data_get($group,'cover_image_path'));
 $avatarUrl = (string) (data_get($group,'avatar_url') ?: data_get($group,'profile_photo_url'));
 $descriptionHtml = (string) (data_get($group, 'description_html') ?: e((string) data_get($group, 'description', '')));
 @endphp

 <x-slot name="header">
 <x-ui.page-header :title="$group->name" :subtitle="$privacyLabel .'·'. $speciesLabel">
 <x-slot:action>
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.button href="{{ route('groups.index') }}" variant="ghost" size="sm">Back</x-ui.button>

 @auth
 @if ($isOwner)
 <x-ui.role-badge role="owner"/>
 @if ($isArchived)
 <form method="POST" action="{{ route('groups.unarchive', $groupRouteKey) }}">
 @csrf
 @method('PATCH')
 <x-ui.button type="submit" variant="primary" size="sm">Restore</x-ui.button>
 </form>
 @else
 <form method="POST" action="{{ route('groups.archive', $groupRouteKey) }}">
 @csrf
 @method('PATCH')
 <x-ui.button type="submit" variant="ghost" size="sm">Archive</x-ui.button>
 </form>
 @endif
 @elseif ($isMember)
 <form method="POST" action="{{ route('groups.leave', $groupRouteKey) }}">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="danger" size="sm">Leave</x-ui.button>
 </form>
 @elseif ($isPendingMembership)
 <x-ui.badge variant="warning" size="md" :dot="true">Request Pending</x-ui.badge>
 <form method="POST" action="{{ route('groups.requests.cancel', $groupRouteKey) }}">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="ghost" size="sm">Cancel</x-ui.button>
 </form>
 @elseif (! $isArchived && $privacyValue !=='secret')
 <form method="POST" action="{{ route('groups.join', $groupRouteKey) }}">
 @csrf
 <x-ui.button type="submit" variant="primary" size="sm">
 {{ $privacyValue ==='public'?'Join Group':'Request to Join'}}
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
 @if ($coverUrl !=='')
 <img src="{{ $coverUrl }}" alt="{{ $group->name }} cover" class="h-full w-full object-cover">
 @else
 <div class="h-full w-full" style="background: linear-gradient(120deg, color-mix(in srgb, var(--color-paw) 24%, var(--color-warm-white) 76%), color-mix(in srgb, var(--color-sky) 22%, var(--color-warm-white) 78%));"></div>
 @endif
 </div>

 <div class="relative px-5 pb-5 pt-0 sm:px-6">
 <div class="-mt-10 flex flex-wrap items-end gap-4">
 <x-avatar :src="$avatarUrl !==''? $avatarUrl : null" :name="$group->name" size="2xl" class="ring-4 ring-warm-white bg-warm-white bg-opacity-100"/>
 
 <div class="min-w-0 flex-1 pb-1">
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.badge variant="default">{{ $membersCount }} members</x-ui.badge>
 <x-ui.badge variant="default">{{ $postsCount }} posts</x-ui.badge>
 <x-ui.badge variant="default">{{ $eventsCount }} events</x-ui.badge>
 @if ($isArchived)
 <x-ui.badge variant="warning">Archived</x-ui.badge>
 @endif
 </div>
 @if (filled((string) $group->description))
 <p class="mt-2 text-sm text-fur">{!! $descriptionHtml !!}</p>
 @endif
 </div>
 </div>
 </div>
 </x-ui.card>

 @php
 $navTabs = [
 ['label'=>'Overview','value'=>'feed'],
 ['label'=>'Members','value'=>'members'],
 ];
 if ($canManageMembers) {
 $navTabs[] = ['label'=>'Requests','value'=>'pending','count'=> $pendingCount];
 }
 $navTabs[] = ['label'=>'About','value'=>'about'];
 
 $currentActivityTab = $activeTab ==='members'&& $requestTab ==='pending'?'pending': $activeTab;
 @endphp

 <div class="mb-6">
 <x-ui.tabs :tabs="$navTabs" :active="$currentActivityTab" paramName="tab"/>
 </div>

 <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_19rem]">
 <section class="space-y-4">
 <x-ui.flash-messages />

 @if ($isArchived)
 <x-ui.card padding="base" class="border-amber-200 bg-amber-50">
 <p class="text-sm font-semibold text-bark">This group is archived.</p>
 <p class="mt-1 text-sm text-fur">Historical posts remain available to members, but new posts and join requests are disabled until the owner restores the group.</p>
 </x-ui.card>
 @endif

 @if (($pendingInvitation ?? null) && ! $isMember)
 <x-ui.card padding="base" class="border-sky-200 bg-sky-50">
 <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
 <div>
 <p class="text-sm font-semibold text-bark">You have an invitation to join this group.</p>
 <p class="mt-1 text-sm text-fur">Accepting adds you as a member and reveals the group feed.</p>
 </div>
 <div class="flex flex-wrap items-center gap-2">
 <form method="POST" action="{{ route('groups.invitations.accept', ['group' => $groupRouteKey, 'invitation' => $pendingInvitation->id]) }}">
 @csrf
 @method('PATCH')
 <x-ui.button type="submit" variant="primary" size="sm">Accept</x-ui.button>
 </form>
 <form method="POST" action="{{ route('groups.invitations.decline', ['group' => $groupRouteKey, 'invitation' => $pendingInvitation->id]) }}">
 @csrf
 @method('PATCH')
 <x-ui.button type="submit" variant="ghost" size="sm">Decline</x-ui.button>
 </form>
 </div>
 </div>
 </x-ui.card>
 @endif

 @if ($activeTab ==='about')
 <x-ui.card padding="lg" class="space-y-6">
 <div>
 <x-ui.section title="About this group" tight />
 <p class="text-sm text-fur">{!! $descriptionHtml !=='' ? $descriptionHtml : 'No description yet.' !!}</p>
 </div>

 <x-ui.divider />

 <div>
 <x-ui.section title="Rules" tight />
 <p class="mt-2 whitespace-pre-line text-sm text-fur">{{ $group->rules ?:'No published rules yet.'}}</p>
 </div>
 </x-ui.card>
 @elseif ($activeTab ==='events')
 <x-ui.card padding="lg">
 <x-ui.card-header title="Group Events"/>

 <div class="mt-4 space-y-3">
 @forelse ($events ?? [] as $eventItem)
 @php
 $startAtRaw = $eventItem->start_at ?? $eventItem->starts_at;
 $location = $eventItem->location_text ?? $eventItem->location;
 $eventStatus = $eventItem->status ??'scheduled';
 @endphp

 <x-ui.card padding="sm" :hover="true" class="flex flex-col gap-1">
 <h4 class="font-semibold">
 <a href="{{ route('events.show', $eventItem->id) }}" class="hover:underline text-bark">{{ $eventItem->title }}</a>
 </h4>
 <p class="text-xs text-fur">
 {{ $startAtRaw ? \Carbon\Carbon::parse($startAtRaw)->format('M j, Y g:i A') :'TBA'}}
 @if ($location)
 · {{ $location }}
 @endif
 </p>
 <div>
 <x-ui.badge variant="{{ strtolower($eventStatus) ==='scheduled'?'warning':'default'}}" size="sm">{{ \Illuminate\Support\Str::headline($eventStatus) }}</x-ui.badge>
 </div>
 </x-ui.card>
 @empty
 <x-ui.empty-state title="No events yet" description="This group hasn't scheduled any events." icon="📅"/>
 @endforelse
 </div>

 @if ($events instanceof \Illuminate\Pagination\LengthAwarePaginator)
 <div class="mt-4">{{ $events->links() }}</div>
 @endif
 </x-ui.card>
 @elseif ($activeTab ==='members')
 @if ($requestTab ==='pending'&& $canManageMembers)
 <x-ui.card padding="lg">
 <x-ui.card-header title="Pending Requests"/>
 <div class="mt-4 space-y-3">
 @forelse ($pendingMembers as $pending)
 <x-ui.user-row :user="$pending->user" subtitle="Pending member" class="border border-whisker/30 rounded-xl px-3 bg-warm-white">
 <x-slot:action>
 <div class="flex items-center gap-2">
 <form method="POST" action="{{ route('groups.requests.approve', ['group'=> $groupRouteKey,'membership'=> $pending->id]) }}">
 @csrf
 <x-ui.button type="submit" variant="success" size="sm">Approve</x-ui.button>
 </form>
 <form method="POST" action="{{ route('groups.requests.reject', ['group'=> $groupRouteKey,'membership'=> $pending->id]) }}">
 @csrf
 <x-ui.button type="submit" variant="ghost" size="sm">Reject</x-ui.button>
 </form>
 </div>
 </x-slot:action>
 </x-ui.user-row>
 @empty
 <x-ui.empty-state title="No pending requests" description="No one is waiting to join the group." icon="📩"/>
 @endforelse
 </div>
 </x-ui.card>
 @else
 <x-ui.card padding="lg">
 <x-ui.card-header title="Members"/>
 <div class="mt-4 space-y-3">
 @forelse ($membersForPage ?? [] as $memberItem)
 @php
 $roleValue = strtolower((string) ($memberItem->role?->value ??'member'));
 @endphp
 <x-ui.user-row :user="$memberItem->user" :role="$roleValue" class="border border-whisker/30 rounded-xl px-3 bg-warm-white">
 <x-slot:action>
 @if ($canManageMembers && $roleValue !=='owner')
 <div class="flex items-center gap-2">
 @if ($isOwner)
 <form method="POST" action="{{ route('groups.ownership.transfer', $groupRouteKey) }}">
 @csrf
 @method('PATCH')
 <input type="hidden" name="membership_id" value="{{ $memberItem->id }}" />
 <x-ui.button type="submit" variant="ghost" size="sm">Make Owner</x-ui.button>
 </form>
 @endif
 @if (in_array($roleValue, ['member','moderator'], true))
 <form method="POST" action="{{ route('groups.members.promote', ['group'=> $groupRouteKey,'membership'=> $memberItem->id]) }}">
 @csrf
 <x-ui.button type="submit" variant="ghost" size="sm">Promote</x-ui.button>
 </form>
 @endif
 @if (in_array($roleValue, ['admin','moderator'], true))
 <form method="POST" action="{{ route('groups.members.demote', ['group'=> $groupRouteKey,'membership'=> $memberItem->id]) }}">
 @csrf
 <x-ui.button type="submit" variant="ghost" size="sm">Demote</x-ui.button>
 </form>
 @endif
 <form method="POST" action="{{ route('groups.members.remove', ['group'=> $groupRouteKey,'membership'=> $memberItem->id]) }}">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit" variant="danger" size="sm">Remove</x-ui.button>
 </form>
 <form method="POST" action="{{ route('groups.bans.store', ['group'=> $groupRouteKey]) }}">
 @csrf
 <input type="hidden" name="user_id" value="{{ $memberItem->user_id }}" />
 <x-ui.button type="submit" variant="ghost" size="sm">Ban</x-ui.button>
 </form>
 </div>
 @endif
 </x-slot:action>
 </x-ui.user-row>
 @empty
 <x-ui.empty-state title="No members yet" description="This group currently has no members." icon="👥"/>
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
 <x-ui.card-header title="Share in this group"/>
 <form method="POST" action="{{ route('groups.posts.store', $groupRouteKey) }}" class="space-y-4" enctype="multipart/form-data">
 @csrf
 <x-ui.input name="post_id" type="number" label="Attach Existing Post ID (optional)" :value="old('post_id')" min="1"/>
 <x-ui.textarea name="body" label="Or create new post" rows="3" placeholder="Write something for this group...">{{ old('body') }}</x-ui.textarea>
 <x-ui.file-upload name="media[]" label="Media (optional)" accept="image/*,video/*" multiple />
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
 <div
 class="space-y-4"
 x-data="groupFeed({
 latestUrl: @js(route('groups.posts.latest', $groupRouteKey)),
 latestPostId: @js((int) ($latestPostId ?? 0)),
 nextUrl: @js($feedPosts instanceof \Illuminate\Contracts\Pagination\Paginator ? $feedPosts->nextPageUrl() : null),
 })"
 >
 <button
 type="button"
 class="hidden w-full rounded-lg border border-sky-200 bg-sky-50 px-4 py-2 text-sm font-semibold text-bark transition hover:bg-sky-100"
 x-bind:class="{ 'hidden': ! hasNewPosts }"
 x-on:click="refreshFeed"
 >
 New group posts available
 </button>

 <div class="space-y-4" data-group-feed-items>
 @forelse ($feedPosts ?? [] as $post)
    <x-post-card :post="$post" :viewer="$viewer"/>
 @empty
 <x-ui.empty-state
 title="No Group Posts Yet"
 description="Start the conversation by sharing the first post."
 />
 @endforelse
 </div>

 @if ($feedPosts instanceof \Illuminate\Contracts\Pagination\Paginator)
 <div class="shell-card p-4" x-show="! nextUrl">{{ $feedPosts->links() }}</div>
 <a
 href="{{ $feedPosts->nextPageUrl() }}"
 data-group-feed-next
 class="sr-only"
 @if (! $feedPosts->hasMorePages()) aria-disabled="true" @endif
 >Next page</a>
 <div
 x-ref="sentinel"
 class="flex h-12 items-center justify-center text-xs font-semibold uppercase tracking-wide text-fur"
 x-show="nextUrl"
 >
 <span x-show="! loadingMore">Loading more posts</span>
 <span x-show="loadingMore">Loading</span>
 </div>
 @endif
 </div>
 @endif
 @endif
 </section>

 <aside class="space-y-4">
 <x-ui.card padding="md">
 <x-ui.card-header title="Group Snapshot"/>
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
 <x-ui.user-row :user="$memberItem->user" :role="strtolower((string) ($memberItem->role?->value ??'member'))" class="border border-whisker/30 rounded-xl px-2.5 bg-warm-white !py-1"/>
 @empty
 <p class="text-sm text-fur">No members yet.</p>
 @endforelse
 </div>
 </x-ui.card>

 @if ($canManageMembers && ! $isArchived)
 <x-ui.card padding="md">
 <x-slot:header>
 <x-ui.card-header title="Invite Member" class="pb-3 mb-3"/>
 </x-slot:header>

 <form method="POST" action="{{ route('groups.invitations.store', $groupRouteKey) }}" class="space-y-3">
 @csrf
 <x-ui.input name="user_id" type="number" min="1" label="User ID" :value="old('user_id')" />
 <x-ui.textarea name="message" label="Message" rows="2" placeholder="Optional note">{{ old('message') }}</x-ui.textarea>
 <x-ui.button type="submit" variant="primary" :full="true" size="sm">Send Invitation</x-ui.button>
 </form>
 </x-ui.card>
 @endif

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
