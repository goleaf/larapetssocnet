<x-app-layout>
 @php
 $groupRouteKey = filled((string) ($group->slug ??'')) ? $group->slug : $group->id;

 $canManage = (bool) ($canManageMembers ?? $isAdmin ?? $isOwner ?? false);

 $membersPaginator = $members
 ?? $activeMembers
 ?? \App\Models\GroupMember::query()
 ->where('group_id', $group->id)
 ->where(function ($query): void {
 $query->whereNull('status')->orWhereIn('status', ['active','accepted']);
 })
 ->with('user:id,name,username')
 ->orderByRaw("CASE role WHEN'owner'THEN 1 WHEN'admin'THEN 2 WHEN'moderator'THEN 3 ELSE 4 END")
 ->orderBy('joined_at')
 ->paginate(20)
 ->withQueryString();

 $membersUrl = Route::has('groups.members')
 ? route('groups.members', $groupRouteKey)
 : route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members']);

 $requestsUrl = Route::has('groups.requests')
 ? route('groups.requests', $groupRouteKey)
 : route('groups.show', ['group'=> $groupRouteKey,'tab'=>'members','request_tab'=>'pending']);
 @endphp

 <x-slot name="header">
 <x-ui.page-header :title="$group->name"subtitle="Group Members">
 <x-slot:action>
 <x-ui.button href="{{ route('groups.show', ['group'=> $groupRouteKey,'tab'=>'feed']) }}"
 variant="ghost"size="sm">Back to Group</x-ui.button>
 </x-slot:action>
 </x-ui.page-header>
 </x-slot>

 <div class="mb-6">
 @php
 $navTabs = [
 ['label'=>'Overview','value'=>'feed','href'=> route('groups.show', ['group'=> $groupRouteKey,'tab'=>'feed'])],
 ['label'=>'Members','value'=>'members','href'=> $membersUrl],
 ];
 if ($canManage) {
 $navTabs[] = ['label'=>'Requests','value'=>'requests','href'=> $requestsUrl];
 }
 @endphp
 <x-ui.tabs :tabs="$navTabs"active="members"/>
 </div>

 <x-ui.card padding="lg">
 <div class="space-y-3">
 @forelse ($membersPaginator as $member)
 @php
 $roleValue = strtolower((string) ($member->role ??'member'));
 @endphp

 <x-ui.user-row :user="$member->user":role="$roleValue"
 class="border border-whisker/30 rounded-xl px-4 bg-warm-white">
 <x-slot:action>
 @if ($canManage && $roleValue !=='owner')
 <div x-data="dropdownState()"class="relative">
 <x-ui.button variant="ghost"size="sm"@click="toggle()">Manage ▾</x-ui.button>

 <div x-show="open"x-cloak @click.outside="close()"
 class="absolute right-0 mt-1 min-w-[200px] bg-warm-white border border-whisker/30 shadow-card-hover rounded-lg z-30 p-2 space-y-2">
 <form method="POST"
 action="{{ route('groups.members.role', ['group'=> $groupRouteKey,'membership'=> $member->id]) }}"
 class="flex items-center gap-2">
 @csrf
 @method('PATCH')
 <select name="role"
 class="block w-full rounded-md border-whisker/50 py-1.5 pl-3 pr-8 text-sm text-bark focus:border-paw focus:ring-paw bg-cream">
 <option value="member"@selected($roleValue ==='member')>Member</option>
 <option value="moderator"@selected($roleValue ==='moderator')>Moderator</option>
 <option value="admin"@selected($roleValue ==='admin')>Admin</option>
 </select>
 <x-ui.icon-button type="submit"variant="ghost"size="sm"
 title="Save">✓</x-ui.icon-button>
 </form>
 <x-ui.divider class="!my-2"/>
 <form method="POST"
 action="{{ route('groups.members.ban', ['group'=> $groupRouteKey,'membership'=> $member->id]) }}">
 @csrf
 @method('PATCH')
 <x-ui.button type="submit"variant="danger":full="true"size="sm">Ban
 Member</x-ui.button>
 </form>
 </div>
 </div>
 @endif
 </x-slot:action>
 </x-ui.user-row>
 @empty
 <x-ui.empty-state title="No Members"description="There are no active members yet."icon="👥"/>
 @endforelse
 </div>

 @if ($membersPaginator instanceof \Illuminate\Pagination\LengthAwarePaginator && $membersPaginator->hasPages())
 <div class="mt-6">{{ $membersPaginator->links() }}</div>
 @endif
 </x-ui.card>
</x-app-layout>