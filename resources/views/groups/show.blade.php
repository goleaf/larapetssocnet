<x-app-layout>
 @php
 $groupRouteKey = $groupRouteKey ?? (filled((string) ($group->slug ??'')) ? $group->slug : $group->id);
 $privacyValue = strtolower((string) ($group->privacy ?? (($group->is_private ?? false) ?'private':'public')));
 $privacyLabel = \Illuminate\Support\Str::headline($privacyValue);
 $speciesLabel = \Illuminate\Support\Str::headline(str_replace(['-','_'],'', (string) data_get($group,'species','all pets')));

 $viewer = auth()->user();
 $membershipStatus = strtolower((string) data_get($membership,'status',''));
 $isPendingMembership = $membership && $membershipStatus ==='pending';

 $canPost = $isMember || $isAdmin || $isOwner;
 $canSeePosts = $privacyValue !=='private'|| $canPost;

 $membersUrl = Route::has('groups.members.index')
 ? route('groups.members.index', $groupRouteKey)
 : route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members']);

 $requestsUrl = Route::has('groups.requests.index')
 ? route('groups.requests.index', ['group'=> $groupRouteKey,'status'=>'pending'])
 : route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members','request_tab'=>'pending']);

 $coverUrl = (string) (data_get($group,'cover_photo_url') ?: data_get($group,'cover_image_path'));
 $avatarUrl = (string) (data_get($group,'avatar_url') ?: data_get($group,'profile_photo_url'));

 $sidebarMembers = collect();

 if ($activeMembers instanceof \Illuminate\Pagination\LengthAwarePaginator) {
 $sidebarMembers = $activeMembers->getCollection()->take(7);
 }

 if ($sidebarMembers->isEmpty() && class_exists(\App\Models\GroupMember::class)) {
 $sidebarMembers = \App\Models\GroupMember::query()
 ->where('group_id', $group->id)
 ->where(function ($query): void {
 $query->whereNull('status')->orWhereIn('status', ['active','accepted']);
 })
 ->with('user:id,name,username')
 ->orderByRaw("CASE role WHEN'owner'THEN 1 WHEN'admin'THEN 2 WHEN'moderator'THEN 3 ELSE 4 END")
 ->orderBy('joined_at')
 ->limit(7)
 ->get();
 }

 $pendingCount = $canManageMembers
 ? ($pendingMembers instanceof \Illuminate\Support\Collection
 ? $pendingMembers->count()
 : \App\Models\GroupMember::query()->where('group_id', $group->id)->where('status','pending')->count())
 : 0;

 $membersForPage = $activeMembers;

 if ($activeTab ==='members'&& !($membersForPage instanceof \Illuminate\Pagination\LengthAwarePaginator)) {
 $membersForPage = \App\Models\GroupMember::query()
 ->where('group_id', $group->id)
 ->where(function ($query): void {
 $query->whereNull('status')->orWhereIn('status', ['active','accepted']);
 })
 ->with('user:id,name,username')
 ->orderByRaw("CASE role WHEN'owner'THEN 1 WHEN'admin'THEN 2 WHEN'moderator'THEN 3 ELSE 4 END")
 ->orderBy('joined_at')
 ->paginate(20)
 ->withQueryString();
 }

 $requestTab = request()->string('request_tab')->toString();
 $showPendingRequests = $activeTab ==='members'&& $canManageMembers && $requestTab ==='pending';
 @endphp

 <x-slot name="header">
 <x-ui.page-header :title="$group->name":subtitle="$privacyLabel .'·'. $speciesLabel":breadcrumbs="[
 ['label'=>'Groups','href'=> route('groups.index')],
 ['label'=> $group->name],
 ]">
 <x-slot name="action">
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.button href="{{ route('groups.index') }}"variant="ghost"size="sm">Back</x-ui.button>

 @auth
 @if ($isOwner)
 <x-ui.badge variant="dark">Owner</x-ui.badge>
 @elseif ($isMember)
 <form method="POST"action="{{ route('groups.leave', $groupRouteKey) }}">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit"variant="ghost"size="sm">Leave</x-ui.button>
 </form>
 @elseif ($isPendingMembership)
 <x-ui.badge variant="warning">Request Pending</x-ui.badge>
 @elseif ($privacyValue !=='secret')
 <form method="POST"action="{{ route('groups.join', $groupRouteKey) }}">
 @csrf
 <x-ui.button type="submit"variant="primary"size="sm">
 {{ $privacyValue ==='public'?'Join Group':'Request to Join'}}
 </x-ui.button>
 </form>
 @endif

 @if ($isAdmin || $isOwner)
 <x-ui.button href="{{ route('groups.edit', $groupRouteKey) }}"variant="outline"
 size="sm">Edit</x-ui.button>
 @endif
 @endauth
 </div>
 </x-slot>
 </x-ui.page-header>
 </x-slot>

 <x-ui.card class="overflow-hidden"padding="none">
 <div class="h-36 w-full sm:h-44">
 @if ($coverUrl !=='')
 <img src="{{ $coverUrl }}"alt="{{ $group->name }} cover"class="h-full w-full object-cover">
 @else
 <div class="h-full w-full"
 style="background: linear-gradient(120deg, color-mix(in srgb, var(--ui-primary) 24%, var(--ui-surface) 76%), color-mix(in srgb, var(--ui-accent) 22%, var(--ui-surface) 78%));">
 </div>
 @endif
 </div>

 <div class="relative px-5 pb-5 pt-0 sm:px-6">
 <div class="-mt-10 flex flex-wrap items-end gap-4">
 <div
 class="h-20 w-20 overflow-hidden rounded-full border-4 border-[color:var(--ui-surface)] bg-[color:var(--ui-surface-muted)] sm:h-24 sm:w-24">
 @if ($avatarUrl !=='')
 <img src="{{ $avatarUrl }}"alt="{{ $group->name }} avatar"class="h-full w-full object-cover">
 @else
 <div class="flex h-full w-full items-center justify-center text-3xl">🐾</div>
 @endif
 </div>

 <div class="min-w-0 flex-1 pb-1">
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.group-type-badge :type="$privacyValue"/>
 <x-ui.badge variant="default">{{ $membersCount }} members</x-ui.badge>
 <x-ui.badge variant="default">{{ $postsCount }} posts</x-ui.badge>
 <x-ui.badge variant="default">{{ $eventsCount }} events</x-ui.badge>
 </div>
 @if (filled((string) $group->description))
 <p class="mt-2 text-sm shell-text-muted">{{ $group->description }}</p>
 @endif
 </div>
 </div>
 </div>
 </x-ui.card>

 <nav class="mt-4 flex flex-wrap gap-2">
 <x-ui.button href="{{ route('groups.show', ['group'=> $groupRouteKey,'tab'=>'feed']) }}"
 :variant="$activeTab ==='feed'?'primary':'ghost'"size="sm">Overview</x-ui.button>
 <x-ui.button href="{{ route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members']) }}"
 :variant="$activeTab ==='members'&& !$showPendingRequests ?'primary':'ghost'"
 size="sm">Members</x-ui.button>
 @if ($canManageMembers)
 <x-ui.button
 href="{{ route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members','request_tab'=>'pending']) }}"
 :variant="$showPendingRequests ?'primary':'ghost'"size="sm">Requests ({{ $pendingCount }})</x-ui.button>
 @endif
 <x-ui.button href="{{ route('groups.show', ['group'=> $groupRouteKey,'tab'=>'about']) }}"
 :variant="$activeTab ==='about'?'primary':'ghost'"size="sm">About</x-ui.button>
 <x-ui.button href="{{ route('groups.show', ['group'=> $groupRouteKey,'tab'=>'events']) }}"
 :variant="$activeTab ==='events'?'primary':'ghost'"size="sm">Events</x-ui.button>
 </nav>

 <div class="mt-4 grid gap-5 lg:grid-cols-[20rem_minmax(0,1fr)] max-w-6xl mx-auto">
 <section class="space-y-4 lg:col-start-2 lg:row-start-1">
 @if (session('status'))
 <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
 @endif

 @if ($errors->any())
 <x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>
 @endif

 @if ($activeTab ==='about')
 <x-ui.card>
 <div class="space-y-5">
 <div>
 <h3 class="text-base font-semibold"style="color: var(--ui-text);">About this group</h3>
 <p class="mt-2 text-sm shell-text-muted">{{ $group->description ?:'No description yet.'}}</p>
 </div>

 <div>
 <h3 class="text-base font-semibold"style="color: var(--ui-text);">Rules</h3>
 <p class="mt-2 whitespace-pre-line text-sm shell-text-muted">
 {{ $group->rules ?:'No published rules yet.'}}
 </p>
 </div>
 </div>
 </x-ui.card>
 @elseif ($activeTab ==='events')
 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Group Events"/>
 </x-slot>

 <div class="space-y-3">
 @forelse ($events ?? [] as $eventItem)
 @php
 $startAtRaw = $eventItem->start_at ?? $eventItem->starts_at;
 $location = $eventItem->location_text ?? $eventItem->location;
 $eventStatus = $eventItem->status ??'scheduled';
 @endphp

 <x-ui.card padding="sm"class="border border-[color:var(--ui-border)] shadow-none">
 <h4 class="font-semibold">
 <a href="{{ route('events.show', $eventItem->id) }}"
 class="hover:underline">{{ $eventItem->title }}</a>
 </h4>
 <p class="mt-1 text-xs shell-text-muted">
 {{ $startAtRaw ? \Carbon\Carbon::parse($startAtRaw)->format('M j, Y g:i A') :'TBA'}}
 @if ($location)
 · {{ $location }}
 @endif
 </p>
 <x-ui.badge class="mt-2"
 variant="default">{{ \Illuminate\Support\Str::headline($eventStatus) }}</x-ui.badge>
 </x-ui.card>
 @empty
 <p class="text-sm shell-text-muted">No events yet.</p>
 @endforelse
 </div>

 @if ($events instanceof \Illuminate\Pagination\LengthAwarePaginator)
 <div class="mt-4">
 <x-ui.pagination :paginator="$events"/>
 </div>
 @endif
 </x-ui.card>
 @elseif ($activeTab ==='members')
 @if ($showPendingRequests)
 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Pending Requests"/>
 </x-slot>

 <div class="space-y-3">
 @forelse ($pendingMembers as $pending)
 <x-ui.user-row :name="$pending->user?->name ??'Unknown user'":subtitle="$pending->user?->username ?'@'. $pending->user->username :'Pending member'">
 <x-slot name="action">
 <div class="flex items-center gap-2">
 <form method="POST"
 action="{{ route('groups.requests.approve', ['group'=> $groupRouteKey,'membership'=> $pending->id]) }}">
 @csrf
 <x-ui.button type="submit"size="xs"variant="primary">Approve</x-ui.button>
 </form>
 <form method="POST"
 action="{{ route('groups.requests.reject', ['group'=> $groupRouteKey,'membership'=> $pending->id]) }}">
 @csrf
 <x-ui.button type="submit"size="xs"variant="ghost">Reject</x-ui.button>
 </form>
 </div>
 </x-slot>
 </x-ui.user-row>
 @empty
 <p class="text-sm shell-text-muted">No pending requests.</p>
 @endforelse
 </div>
 </x-ui.card>
 @else
 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Members"/>
 </x-slot>

 <div class="space-y-3">
 @forelse ($membersForPage ?? [] as $memberItem)
 @php
 $roleValue = strtolower((string) ($memberItem->role ??'member'));
 @endphp

 <x-ui.user-row :name="$memberItem->user?->name ??'Unknown user'"
 :subtitle="$memberItem->user?->username ?'@'. $memberItem->user->username :'Member'">
 <x-slot name="action">
 <div class="flex flex-wrap items-center gap-2">
 <x-ui.role-badge :role="$roleValue"/>

 @if ($canManageMembers && $roleValue !=='owner')
 @if (in_array($roleValue, ['member','moderator'], true))
 <form method="POST"
 action="{{ route('groups.members.promote', ['group'=> $groupRouteKey,'membership'=> $memberItem->id]) }}">
 @csrf
 <x-ui.button type="submit"size="xs"variant="ghost">Promote</x-ui.button>
 </form>
 @endif

 @if (in_array($roleValue, ['admin','moderator'], true))
 <form method="POST"
 action="{{ route('groups.members.demote', ['group'=> $groupRouteKey,'membership'=> $memberItem->id]) }}">
 @csrf
 <x-ui.button type="submit"size="xs"variant="ghost">Demote</x-ui.button>
 </form>
 @endif

 <form method="POST"
 action="{{ route('groups.members.remove', ['group'=> $groupRouteKey,'membership'=> $memberItem->id]) }}"
 onsubmit="return confirm('Remove this member from the group?');">
 @csrf
 @method('DELETE')
 <x-ui.button type="submit"size="xs"variant="danger">Remove</x-ui.button>
 </form>
 @endif
 </div>
 </x-slot>
 </x-ui.user-row>
 @empty
 <p class="text-sm shell-text-muted">No members yet.</p>
 @endforelse
 </div>

 @if ($membersForPage instanceof \Illuminate\Pagination\LengthAwarePaginator)
 <div class="mt-4">
 <x-ui.pagination :paginator="$membersForPage"/>
 </div>
 @endif
 </x-ui.card>
 @endif
 @else
 @if ($canPost)
 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Share in this group"/>
 </x-slot>

 <form method="POST"action="{{ route('groups.posts.store', $groupRouteKey) }}"
 enctype="multipart/form-data"class="space-y-3">
 @csrf
 <x-ui.textarea name="body"label="Post"rows="3"placeholder="Write something for this group..."
 :error="$errors->first('body')">{{ old('body') }}</x-ui.textarea>

 <x-ui.file-upload name="media"label="Media"multiple
 accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/webm"
 :error="$errors->first('media') ?: $errors->first('media.*')"
 hint="Optional: add up to 4 files."/>

 <div class="flex justify-end">
 <x-ui.button type="submit"variant="primary"size="sm">Publish</x-ui.button>
 </div>
 </form>
 </x-ui.card>
 @endif

 @if (!$canSeePosts)
 <x-ui.card>
 <x-ui.empty-state title="Private group content"
 description="Join this group to view and participate in posts."icon="🔒"/>
 </x-ui.card>
 @else
 <div class="space-y-4">
 @forelse ($feedPosts ?? [] as $post)
 @include('partials.post-card', ['post'=> $post,'viewer'=> $viewer])
 @empty
 <x-ui.empty-state title="No Group Posts Yet"
 description="Start the conversation by sharing the first post."/>
 @endforelse

 @if ($feedPosts instanceof \Illuminate\Pagination\LengthAwarePaginator)
 <x-ui.card>
 <x-ui.pagination :paginator="$feedPosts"/>
 </x-ui.card>
 @endif
 </div>
 @endif
 @endif
 </section>

 <aside class="space-y-4 lg:col-start-1 lg:row-start-1 lg:sticky lg:top-24 lg:self-start">
 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Group Snapshot"/>
 </x-slot>

 <div class="grid grid-cols-1 gap-3">
 <x-ui.stat label="Members":value="$membersCount"icon="👥"/>
 <x-ui.stat label="Posts":value="$postsCount"icon="📝"/>
 <x-ui.stat label="Events":value="$eventsCount"icon="📅"/>
 </div>
 </x-ui.card>

 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Members">
 <x-slot name="action">
 <x-ui.button href="{{ $membersUrl }}"variant="ghost"size="xs">View all</x-ui.button>
 </x-slot>
 </x-ui.card-header>
 </x-slot>

 <div class="space-y-2">
 @forelse ($sidebarMembers as $memberItem)
 <x-ui.user-row :name="$memberItem->user?->name ??'Unknown user'"
 :subtitle="$memberItem->user?->username ?'@'. $memberItem->user->username :'Member'">
 <x-slot name="action">
 <x-ui.badge size="sm"
 variant="default">{{ \Illuminate\Support\Str::headline((string) ($memberItem->role ??'member')) }}</x-ui.badge>
 </x-slot>
 </x-ui.user-row>
 @empty
 <p class="text-sm shell-text-muted">No members yet.</p>
 @endforelse
 </div>
 </x-ui.card>

 @if ($canManageMembers)
 <x-ui.card>
 <x-slot name="header">
 <x-ui.card-header title="Join Requests">
 <x-slot name="action">
 <x-ui.badge variant="warning">{{ $pendingCount }}</x-ui.badge>
 </x-slot>
 </x-ui.card-header>
 </x-slot>

 @if ($pendingCount > 0)
 <p class="text-sm shell-text-muted">You have pending member requests to review.</p>
 <div class="mt-3">
 <x-ui.button href="{{ $requestsUrl }}"variant="primary"full size="sm">Review
 Requests</x-ui.button>
 </div>
 @else
 <p class="text-sm shell-text-muted">No pending requests right now.</p>
 @endif
 </x-ui.card>
 @endif
 </aside>
 </div>
</x-app-layout>